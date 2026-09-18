<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerInvoice;
use App\Models\CustomerReceipt;
use App\Services\LedgerService;
use App\Support\Accounting as AccountingSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminArController extends Controller
{
    protected function ensureFeatureEnabled(): void
    {
        if (!AccountingSupport::feature('ar_ap')) {
            abort(404);
        }
        if (!AccountingSupport::tableExists('customer_invoices')) {
            abort(404);
        }
    }

    public function invoices(Request $request)
    {
        $this->ensureFeatureEnabled();

        $q = trim((string) $request->input('q', ''));
        $query = CustomerInvoice::query()->with('customer:id,name,email,phone')->latest();
        if ($q !== '') {
            $query->where(function ($qq) use ($q) {
                $qq->where('number', 'like', "%{$q}%")
                   ->orWhere('status', 'like', "%{$q}%")
                   ->orWhereHas('customer', function ($sq) use ($q) {
                       $sq->where('name', 'like', "%{$q}%")
                          ->orWhere('email', 'like', "%{$q}%")
                          ->orWhere('phone', 'like', "%{$q}%");
                   });
            });
        }
        return response()->json($query->paginate(20));
    }

    public function showInvoice($id)
    {
        $this->ensureFeatureEnabled();
        $invoice = CustomerInvoice::with(['customer:id,name,email,phone', 'ledgerJournal.entries'])->findOrFail($id);
        return response()->json($invoice);
    }

    public function createInvoice(Request $request)
    {
        $this->ensureFeatureEnabled();

        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'number' => ['nullable', 'string', 'max:50'],
            'date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:date'],
            'currency' => ['nullable', 'string', 'max:3'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $data['status'] = 'draft';

        $invoice = CustomerInvoice::create($data);
        return response()->json(['message' => 'Invoice created', 'invoice' => $invoice]);
    }

    public function updateInvoice(Request $request, $id)
    {
        $this->ensureFeatureEnabled();
        $invoice = CustomerInvoice::findOrFail($id);
        if ($invoice->status !== 'draft') {
            return response()->json(['message' => 'Only draft invoices can be edited'], 422);
        }

        $data = $request->validate([
            'number' => ['nullable', 'string', 'max:50'],
            'date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:date'],
            'currency' => ['nullable', 'string', 'max:3'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $invoice->update($data);
        return response()->json(['message' => 'Invoice updated', 'invoice' => $invoice]);
    }

    public function postInvoice(Request $request, $id)
    {
        $this->ensureFeatureEnabled();
        $invoice = CustomerInvoice::findOrFail($id);
        if (!in_array($invoice->status, ['draft', 'void'])) {
            return response()->json(['message' => 'Only draft/void invoices can be posted'], 422);
        }

        $payload = $request->validate([
            'ar_account_id' => ['required', 'exists:ledger_accounts,id'],
            'revenue_account_id' => ['required', 'exists:ledger_accounts,id'],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            DB::transaction(function () use ($invoice, $payload) {
                $journal = app(LedgerService::class)->record([
                    'date' => $invoice->date,
                    'reference' => $payload['reference'] ?? $invoice->number,
                    'description' => $payload['description'] ?? ('Invoice '.$invoice->number),
                    'created_by' => auth()->id(),
                ], [
                    [
                        'ledger_account_id' => (int) $payload['ar_account_id'],
                        'debit' => (float) $invoice->amount,
                        'credit' => 0,
                        'description' => 'Accounts Receivable',
                    ],
                    [
                        'ledger_account_id' => (int) $payload['revenue_account_id'],
                        'debit' => 0,
                        'credit' => (float) $invoice->amount,
                        'description' => 'Revenue',
                    ],
                ]);

                $invoice->ledger_journal_id = $journal->id;
                $invoice->status = 'posted';
                $invoice->save();

                // Lock journal if supported
                app(LedgerService::class)->postJournal($journal, auth()->id());
            });
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Invoice posted', 'invoice' => $invoice->fresh()]);
    }

    public function receipts(Request $request)
    {
        $this->ensureFeatureEnabled();
        if (!AccountingSupport::tableExists('customer_receipts')) {
            abort(404);
        }

        $query = CustomerReceipt::query()->with('invoice');
        return response()->json($query->latest()->paginate(20));
    }

    public function receivePayment(Request $request, $id)
    {
        $this->ensureFeatureEnabled();
        if (!AccountingSupport::tableExists('customer_receipts')) {
            abort(404);
        }

        $invoice = CustomerInvoice::findOrFail($id);
        if ($invoice->status !== 'posted') {
            return response()->json(['message' => 'Only posted invoices can be receipted'], 422);
        }

        $data = $request->validate([
            'date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.$invoice->amount],
            'reference' => ['nullable', 'string', 'max:255'],
            'bank_or_cash_account_id' => ['required', 'exists:ledger_accounts,id'],
            'ar_account_id' => ['required', 'exists:ledger_accounts,id'],
        ]);

        try {
            DB::transaction(function () use ($invoice, $data) {
                $journal = app(LedgerService::class)->record([
                    'date' => $data['date'],
                    'reference' => $data['reference'] ?? ('RCPT-'.$invoice->number),
                    'description' => 'Customer receipt for invoice '.$invoice->number,
                    'created_by' => auth()->id(),
                ], [
                    [
                        'ledger_account_id' => (int) $data['bank_or_cash_account_id'],
                        'debit' => (float) $data['amount'],
                        'credit' => 0,
                        'description' => 'Cash/Bank',
                    ],
                    [
                        'ledger_account_id' => (int) $data['ar_account_id'],
                        'debit' => 0,
                        'credit' => (float) $data['amount'],
                        'description' => 'Accounts Receivable',
                    ],
                ]);

                $receipt = CustomerReceipt::create([
                    'customer_invoice_id' => $invoice->id,
                    'date' => $data['date'],
                    'amount' => $data['amount'],
                    'reference' => $data['reference'] ?? null,
                    'ledger_journal_id' => $journal->id,
                ]);

                // If fully paid, mark invoice paid
                $paid = (float) CustomerReceipt::where('customer_invoice_id', $invoice->id)->sum('amount');
                if ($paid >= (float) $invoice->amount) {
                    $invoice->status = 'paid';
                    $invoice->save();
                }

                app(LedgerService::class)->postJournal($journal, auth()->id());
            });
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Receipt recorded', 'invoice' => $invoice->fresh(['ledgerJournal'])]);
    }

    public function voidInvoice(Request $request, $id)
    {
        $this->ensureFeatureEnabled();
        $invoice = CustomerInvoice::findOrFail($id);
        if ($invoice->status === 'paid') {
            return response()->json(['message' => 'Cannot void a paid invoice'], 422);
        }
        $invoice->status = 'void';
        $invoice->save();
        return response()->json(['message' => 'Invoice voided', 'invoice' => $invoice]);
    }
}
