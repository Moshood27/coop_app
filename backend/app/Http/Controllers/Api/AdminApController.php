<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VendorBill;
use App\Models\VendorPayment;
use App\Services\LedgerService;
use App\Support\Accounting as AccountingSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminApController extends Controller
{
    protected function ensureFeatureEnabled(): void
    {
        if (!AccountingSupport::feature('ar_ap')) {
            abort(404);
        }
        if (!AccountingSupport::tableExists('vendor_bills')) {
            abort(404);
        }
    }

    public function bills(Request $request)
    {
        $this->ensureFeatureEnabled();
        $q = trim((string) $request->input('q', ''));
        $query = VendorBill::query()->with('vendor:id,name,phone,email')->latest();
        if ($q !== '') {
            $query->where(function ($qq) use ($q) {
                $qq->where('number', 'like', "%{$q}%")
                   ->orWhere('status', 'like', "%{$q}%")
                   ->orWhereHas('vendor', function ($sq) use ($q) {
                       $sq->where('name', 'like', "%{$q}%");
                   });
            });
        }
        return response()->json($query->paginate(20));
    }

    public function showBill($id)
    {
        $this->ensureFeatureEnabled();
        $bill = VendorBill::with(['vendor:id,name,phone,email', 'ledgerJournal.entries'])->findOrFail($id);
        return response()->json($bill);
    }

    public function createBill(Request $request)
    {
        $this->ensureFeatureEnabled();
        $data = $request->validate([
            'vendor_id' => ['required', 'exists:vendors,id'],
            'number' => ['nullable', 'string', 'max:50'],
            'date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:date'],
            'currency' => ['nullable', 'string', 'max:3'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $data['status'] = 'draft';
        $bill = VendorBill::create($data);
        return response()->json(['message' => 'Vendor bill created', 'bill' => $bill]);
    }

    public function updateBill(Request $request, $id)
    {
        $this->ensureFeatureEnabled();
        $bill = VendorBill::findOrFail($id);
        if ($bill->status !== 'draft') {
            return response()->json(['message' => 'Only draft bills can be edited'], 422);
        }
        $data = $request->validate([
            'number' => ['nullable', 'string', 'max:50'],
            'date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:date'],
            'currency' => ['nullable', 'string', 'max:3'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);
        $bill->update($data);
        return response()->json(['message' => 'Vendor bill updated', 'bill' => $bill]);
    }

    public function postBill(Request $request, $id)
    {
        $this->ensureFeatureEnabled();
        $bill = VendorBill::findOrFail($id);
        if (!in_array($bill->status, ['draft', 'void'])) {
            return response()->json(['message' => 'Only draft/void bills can be posted'], 422);
        }

        $payload = $request->validate([
            'ap_account_id' => ['required', 'exists:ledger_accounts,id'],
            'expense_account_id' => ['required', 'exists:ledger_accounts,id'],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            DB::transaction(function () use ($bill, $payload) {
                $journal = app(LedgerService::class)->record([
                    'date' => $bill->date,
                    'reference' => $payload['reference'] ?? $bill->number,
                    'description' => $payload['description'] ?? ('Vendor bill '.$bill->number),
                    'created_by' => auth()->id(),
                ], [
                    [
                        'ledger_account_id' => (int) $payload['expense_account_id'],
                        'debit' => (float) $bill->amount,
                        'credit' => 0,
                        'description' => 'Expense',
                    ],
                    [
                        'ledger_account_id' => (int) $payload['ap_account_id'],
                        'debit' => 0,
                        'credit' => (float) $bill->amount,
                        'description' => 'Accounts Payable',
                    ],
                ]);

                $bill->ledger_journal_id = $journal->id;
                $bill->status = 'posted';
                $bill->save();

                app(LedgerService::class)->postJournal($journal, auth()->id());
            });
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Bill posted', 'bill' => $bill->fresh()]);
    }

    public function payments(Request $request)
    {
        $this->ensureFeatureEnabled();
        if (!AccountingSupport::tableExists('vendor_payments')) {
            abort(404);
        }
        $query = VendorPayment::query()->with('bill');
        return response()->json($query->latest()->paginate(20));
    }

    public function payBill(Request $request, $id)
    {
        $this->ensureFeatureEnabled();
        if (!AccountingSupport::tableExists('vendor_payments')) {
            abort(404);
        }

        $bill = VendorBill::findOrFail($id);
        if ($bill->status !== 'posted') {
            return response()->json(['message' => 'Only posted bills can be paid'], 422);
        }

        $data = $request->validate([
            'date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.$bill->amount],
            'reference' => ['nullable', 'string', 'max:255'],
            'bank_or_cash_account_id' => ['required', 'exists:ledger_accounts,id'],
            'ap_account_id' => ['required', 'exists:ledger_accounts,id'],
        ]);

        try {
            DB::transaction(function () use ($bill, $data) {
                $journal = app(LedgerService::class)->record([
                    'date' => $data['date'],
                    'reference' => $data['reference'] ?? ('PAY-'.$bill->number),
                    'description' => 'Payment for vendor bill '.$bill->number,
                    'created_by' => auth()->id(),
                ], [
                    [
                        'ledger_account_id' => (int) $data['ap_account_id'],
                        'debit' => (float) $data['amount'],
                        'credit' => 0,
                        'description' => 'Accounts Payable',
                    ],
                    [
                        'ledger_account_id' => (int) $data['bank_or_cash_account_id'],
                        'debit' => 0,
                        'credit' => (float) $data['amount'],
                        'description' => 'Cash/Bank',
                    ],
                ]);

                $payment = VendorPayment::create([
                    'vendor_bill_id' => $bill->id,
                    'date' => $data['date'],
                    'amount' => $data['amount'],
                    'reference' => $data['reference'] ?? null,
                    'ledger_journal_id' => $journal->id,
                ]);

                // If fully paid, mark bill paid
                $paid = (float) VendorPayment::where('vendor_bill_id', $bill->id)->sum('amount');
                if ($paid >= (float) $bill->amount) {
                    $bill->status = 'paid';
                    $bill->save();
                }

                app(LedgerService::class)->postJournal($journal, auth()->id());
            });
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Payment recorded', 'bill' => $bill->fresh(['ledgerJournal'])]);
    }

    public function voidBill(Request $request, $id)
    {
        $this->ensureFeatureEnabled();
        $bill = VendorBill::findOrFail($id);
        if ($bill->status === 'paid') {
            return response()->json(['message' => 'Cannot void a paid bill'], 422);
        }
        $bill->status = 'void';
        $bill->save();
        return response()->json(['message' => 'Bill voided', 'bill' => $bill]);
    }
}
