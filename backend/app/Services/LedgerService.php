<?php

namespace App\Services;

use App\Models\LedgerJournal;
use App\Models\LedgerAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Exception;

class LedgerService
{
    /**
     * Create a balanced journal entry.
     *
     * @param array $data ['date', 'reference', 'description', 'created_by']
     * @param array $entries [['ledger_account_id', 'debit', 'credit', 'description'], ...]
     * @return LedgerJournal
     * @throws Exception
     */
    public function record(array $data, array $entries): LedgerJournal
    {
        return DB::transaction(function () use ($data, $entries) {
            // Assign a sequential journal number if available
            $number = null;
            try {
                if (Schema::hasTable('journal_sequences') && Schema::hasColumn('ledger_journals', 'number')) {
                    $number = app(JournalNumberService::class)->nextNumber(optional($data['date'] ?? null) ? (int) date('Y', strtotime((string) $data['date'])) : null);
                }
            } catch (\Throwable $e) {
                // In case migrations aren't applied yet in some environments, fail open without number
                $number = null;
            }

            $journalDate = $data['date'] ?? now();

            // Period lock enforcement (guarded for pre-migration environments)
            try {
                if (Schema::hasTable('fiscal_periods')) {
                    $periodService = app(PeriodService::class);
                    if (!$periodService->isDateOpen($journalDate)) {
                        throw new Exception("Cannot post journal: fiscal period is closed for date " . date('Y-m-d', strtotime((string)$journalDate)) . ".");
                    }
                    $period = $periodService->findForDate($journalDate);
                    $periodId = optional($period)->id;
                } else {
                    $periodId = null;
                }
            } catch (\Throwable $e) {
                $periodId = null; // fail open
            }

            // Idempotency via external key (guarded)
            if (!empty($data['external_key'])) {
                try {
                    if (Schema::hasColumn('ledger_journals', 'external_key')) {
                        $exists = LedgerJournal::where('external_key', $data['external_key'])->exists();
                        if ($exists) {
                            throw new Exception("Duplicate posting detected for external_key: {$data['external_key']}");
                        }
                    }
                } catch (\Throwable $e) {
                    // ignore check if schema not ready
                }
            }

            $payload = [
                'date' => $journalDate,
                'number' => $data['number'] ?? $number,
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
                'created_by' => $data['created_by'] ?? null,
            ];

            // Optional new columns (guarded)
            try {
                if (Schema::hasColumn('ledger_journals', 'period_id')) {
                    $payload['period_id'] = $periodId ?? null;
                }
                if (Schema::hasColumn('ledger_journals', 'status')) {
                    $payload['status'] = 'posted';
                }
                if (Schema::hasColumn('ledger_journals', 'posted_at')) {
                    $payload['posted_at'] = now();
                }
                if (Schema::hasColumn('ledger_journals', 'external_key') && !empty($data['external_key'])) {
                    $payload['external_key'] = $data['external_key'];
                }
                if (Schema::hasColumn('ledger_journals', 'currency_code') && !empty($data['currency_code'])) {
                    $payload['currency_code'] = strtoupper($data['currency_code']);
                }
                if (Schema::hasColumn('ledger_journals', 'fx_rate') && !empty($data['fx_rate'])) {
                    $payload['fx_rate'] = $data['fx_rate'];
                }
            } catch (\Throwable $e) {
                // ignore population of optional columns
            }

            $journal = LedgerJournal::create($payload);

            foreach ($entries as $entryData) {
                $journal->entries()->create([
                    'ledger_account_id' => $entryData['ledger_account_id'],
                    'debit' => $entryData['debit'] ?? 0,
                    'credit' => $entryData['credit'] ?? 0,
                    'description' => $entryData['description'] ?? null,
                    'branch_id' => $entryData['branch_id'] ?? ($data['branch_id'] ?? null),
                ]);
            }

            if (!$journal->isBalanced()) {
                throw new Exception("Journal entry is not balanced. Total debits must equal total credits.");
            }

            return $journal;
        });
    }

    /**
     * Record a journal entry using account codes instead of IDs.
     *
     * @param array $data
     * @param array $entries [['code', 'debit', 'credit', 'description'], ...]
     * @return LedgerJournal
     * @throws Exception
     */
    public function recordByCode(array $data, array $entries): LedgerJournal
    {
        $resolvedEntries = array_map(function ($entry) {
            if (isset($entry['code'])) {
                $account = LedgerAccount::where('code', $entry['code'])->first();
                if (!$account) {
                    throw new Exception("Ledger account with code {$entry['code']} not found.");
                }
                $entry['ledger_account_id'] = $account->id;
                unset($entry['code']);
            }
            return $entry;
        }, $entries);

        return $this->record($data, $resolvedEntries);
    }

    /**
     * Get the balance of an account by code.
     */
    public function getBalance(string $code): float
    {
        $account = LedgerAccount::where('code', $code)->firstOrFail();
        return $account->balance;
    }

    /**
     * Record a member contribution.
     * Debit Bank, Credit Member Deposits (or Equity for shares).
     */
    public function recordContribution(\App\Models\Contribution $contribution): LedgerJournal
    {
        $creditAccount = '2200'; // Default: Member Deposits (Liability)

        if ($contribution->scheme && str_contains(strtolower($contribution->scheme->name), 'share')) {
            $creditAccount = '3100'; // Member Equity
        } elseif ($contribution->scheme && strtoupper($contribution->scheme->name) === 'SITTING') {
            $creditAccount = '4200'; // Fine/Sitting Fee Income
        }

        return $this->recordByCode([
            'date' => $contribution->created_at ?? now(),
            'reference' => $contribution->reference,
            'description' => "Contribution from " . ($contribution->user->name ?? 'User') . " for " . ($contribution->scheme->name ?? 'Unknown Scheme'),
            'branch_id' => optional($contribution->user)->branch_id,
        ], [
            ['code' => '1100', 'debit' => $contribution->amount, 'description' => 'Bank Deposit'],
            ['code' => $creditAccount, 'credit' => $contribution->amount, 'description' => "Contribution Receipt"],
        ]);
    }

    /**
     * Record a fine payment.
     * Debit Bank (or Member Deposit), Credit Fine Income.
     */
    public function recordFine(\App\Models\Contribution $contribution): LedgerJournal
    {
        return $this->recordByCode([
            'date' => $contribution->created_at ?? now(),
            'reference' => $contribution->reference,
            'description' => "Fine payment from {$contribution->user->name}",
            'branch_id' => optional($contribution->user)->branch_id,
        ], [
            ['code' => '1100', 'debit' => $contribution->amount, 'description' => 'Bank Deposit'],
            ['code' => '4200', 'credit' => $contribution->amount, 'description' => 'Fine Income'],
        ]);
    }

    /**
     * Record a loan disbursement.
     * Debit Loans Receivable, Credit Bank.
     */
    public function recordLoanDisbursement(\App\Models\QardHasan $loan): LedgerJournal
    {
        $principal = (float) $loan->principal_amount;

        // No deductions from payout as per requirements.
        // Admin fees are recorded as income but NOT subtracted from the bank withdrawal.
        $adminFeeFlat = (float) ($loan->admin_fee_flat ?? 0);
        $adminFeePct = (float) ($loan->admin_fee_pct ?? 0);
        $totalFee = $adminFeeFlat + ($principal * ($adminFeePct / 100));

        $entries = [
            ['code' => '1300', 'debit' => $principal, 'description' => 'Loan Asset (Principal)'],
            ['code' => '1100', 'credit' => $principal, 'description' => 'Bank Withdrawal (Full Principal)'],
        ];

        // If there is a fee, it is recorded elsewhere per business rule (no deduction from payout).

        return $this->recordByCode([
            'date' => $loan->approved_at ?? now(),
            'reference' => $loan->qard_id_string,
            'description' => "Qard Hasan Disbursement to {$loan->user->name}",
            'branch_id' => optional($loan->user)->branch_id,
        ], $entries);
    }

    /**
     * Record a loan repayment.
     * Debit Bank, Credit Loans Receivable.
     */
    public function recordLoanRepayment(\App\Models\QardHasanRepayment $repayment): LedgerJournal
    {
        $debitAccount = '1100'; // Bank
        $debitDesc = 'Bank Deposit';

        if (str_starts_with((string) $repayment->reference, 'TAKAFUL_PAYOUT')) {
            $debitAccount = '2210'; // Takaful Pool Fund
            $debitDesc = 'Takaful Pool Settlement';
        }

        return $this->recordByCode([
            'date' => $repayment->paid_at ?? now(),
            'reference' => $repayment->reference,
            'description' => "Qard Hasan Repayment from {$repayment->qardHasan->user->name}",
            'branch_id' => optional($repayment->qardHasan->user)->branch_id,
        ], [
            ['code' => $debitAccount, 'debit' => $repayment->amount, 'description' => $debitDesc],
            ['code' => '1300', 'credit' => $repayment->amount, 'description' => 'Loan Asset Reduction'],
        ]);
    }
    /**
     * Record a wallet credit (external funding).
     * Debit Bank, Credit Member Deposits.
     */
    public function recordWalletCredit(\App\Models\WalletTransaction $tx): LedgerJournal
    {
        $actualAmount = (float) $tx->amount;
        $maintenanceCharge = (float) ($tx->meta['maintenance_charge'] ?? 0);
        $grossAmount = $actualAmount + $maintenanceCharge;

        $entries = [
            ['code' => '2200', 'credit' => $actualAmount, 'description' => "Member Deposit ({$tx->user->membership_number})"],
        ];

        if ($maintenanceCharge > 0) {
            $entries[] = ['code' => '1100', 'debit' => $grossAmount, 'description' => 'Bank Deposit (Gross)'];
            $entries[] = ['code' => '4500', 'credit' => $maintenanceCharge, 'description' => 'Management Fee Income (Maintenance)'];
        } else {
            // Also check for vendor_payout which is an internal transfer
            if ($tx->source === 'vendor_payout') {
                // Dr Accounts Payable (or Cost of Sales), Cr Member Deposit
                return $this->recordByCode([
                    'date' => $tx->created_at ?? now(),
                    'reference' => $tx->reference,
                    'description' => "Vendor Payout to {$tx->user->name}",
                ], [
                    ['code' => '2000', 'debit' => $actualAmount, 'description' => 'Accounts Payable Settlement'],
                    ['code' => '2200', 'credit' => $actualAmount, 'description' => "Member Deposit ({$tx->user->membership_number})"],
                ]);
            }

            $entries[] = ['code' => '1100', 'debit' => $actualAmount, 'description' => 'Bank Deposit'];
        }

        return $this->recordByCode([
            'date' => $tx->created_at ?? now(),
            'reference' => $tx->reference,
            'description' => "Wallet Credit for {$tx->user->name} via {$tx->source}",
            'branch_id' => optional($tx->user)->branch_id,
        ], $entries);
    }

    /**
     * Record a wallet debit (external withdrawal).
     * Debit Member Deposits, Credit Bank.
     */
    public function recordWalletDebit(\App\Models\WalletTransaction $tx): LedgerJournal
    {
        $creditAccount = '1100'; // Default: Bank
        $creditDescription = 'Bank Withdrawal';

        // Map internal charges to income instead of bank withdrawal
        if (in_array($tx->source, ['attendance_fine', 'attendance_fine_collection', 'loan_penalty'])) {
            $creditAccount = '4200'; // Fine/Sitting Fee Income
            $creditDescription = 'Internal Income (Fine)';
        } elseif ($tx->source === 'admin_charge') {
            $creditAccount = '4500'; // Management Fee Income
            $creditDescription = 'Internal Income (Admin Charge)';
        } elseif ($tx->source === 'takaful_contribution') {
            // Internal movement to pool
            $creditAccount = '2210';
            $creditDescription = 'Takaful Pool Transfer';
        } elseif (in_array($tx->source, ['store_installment', 'store_installment_auto'])) {
            $creditAccount = '1310';
            $creditDescription = 'Murabahah Receivable Reduction';
        }

        return $this->recordByCode([
            'date' => $tx->created_at ?? now(),
            'reference' => $tx->reference,
            'description' => "Wallet Debit for {$tx->user->name} via {$tx->source}",
            'branch_id' => optional($tx->user)->branch_id,
        ], [
            ['code' => '2200', 'debit' => $tx->amount, 'description' => "Member Withdrawal ({$tx->user->membership_number})"],
            ['code' => $creditAccount, 'credit' => $tx->amount, 'description' => $creditDescription],
        ]);
    }

    /**
     * Record a Takaful contribution.
     * Debit Bank, Credit Takaful Pool Fund.
     */
    public function recordTakafulContribution(\App\Models\TakafulContribution $contribution): LedgerJournal
    {
        return $this->recordByCode([
            'date' => $contribution->created_at ?? now(),
            'reference' => $contribution->reference,
            'description' => "Takaful Contribution from {$contribution->user->name}",
            'branch_id' => optional($contribution->user)->branch_id,
        ], [
            ['code' => '1100', 'debit' => $contribution->amount, 'description' => 'Bank Deposit'],
            ['code' => '2210', 'credit' => $contribution->amount, 'description' => 'Takaful Pool Fund Credit'],
        ]);
    }

    /**
     * Record general income.
     * Debit Bank, Credit Income.
     */
    public function recordIncome(\App\Models\IncomeEntry $income): LedgerJournal
    {
        return $this->recordByCode([
            'date' => $income->date ?? now(),
            'reference' => 'INC-' . $income->id,
            'description' => "Income: {$income->title} ({$income->category})",
            'branch_id' => $income->branch_id ?? null,
        ], [
            ['code' => '1100', 'debit' => $income->amount, 'description' => 'Bank Deposit'],
            ['code' => '4000', 'credit' => $income->amount, 'description' => "Income - {$income->category}"],
        ]);
    }

    /**
     * Record general expense.
     * Debit Expense, Credit Bank.
     */
    public function recordExpense(\App\Models\ExpenseEntry $expense): LedgerJournal
    {
        $debitAccount = '5000'; // Operating Expenses

        if (stripos((string) $expense->category, 'takaful') !== false) {
            $debitAccount = '2210'; // Takaful Pool Fund (Liability reduction)
        } elseif (stripos((string) $expense->category, 'charity') !== false || stripos((string) $expense->category, 'zakat') !== false) {
            $debitAccount = '2220'; // Charity Fund (Restricted)
        }

        return $this->recordByCode([
            'date' => $expense->date ?? now(),
            'reference' => $expense->payout_reference ?? 'EXP-' . $expense->id,
            'description' => "Expense: {$expense->title} ({$expense->category})",
            'branch_id' => $expense->branch_id ?? null,
        ], [
            ['code' => $debitAccount, 'debit' => $expense->amount, 'description' => "Expense - {$expense->category}"],
            ['code' => '1100', 'credit' => $expense->amount, 'description' => 'Bank Withdrawal'],
        ]);
    }
    /**
     * Record a store order sale (Murabahah).
     * Debit Member Deposits/Cash, Credit Murabahah Income & Inventory/Cost.
     */
    public function recordStoreOrder(\App\Models\StoreOrder $order): LedgerJournal
    {
        $journal = $this->recordByCode([
            'date' => $order->created_at ?? now(),
            'reference' => $order->reference,
            'description' => "Store Order: {$order->reference} from {$order->user->name}",
            'branch_id' => optional($order->user)->branch_id,
        ], [
            ['code' => '1100', 'debit' => $order->total_amount, 'description' => 'Bank Receipt'],
            ['code' => '4400', 'credit' => $order->total_profit, 'description' => 'Murabahah Profit'],
            ['code' => '2000', 'credit' => $order->total_cost, 'description' => 'Accounts Payable (Vendor Portion)'],
        ]);

        // Optional VAT reclassification and COGS posting (guarded by schema checks)
        try {
            // VAT Output: reclassify portion of profit to VAT Output liability if tax tables exist
            if (\Illuminate\Support\Facades\Schema::hasTable('tax_rates')) {
                $taxService = app(\App\Services\TaxService::class);
                $vat = $taxService->computeOrderOutputTax($order);
                $taxAmount = (float) ($vat['total_tax'] ?? 0);
                if ($taxAmount > 0.0) {
                    // Dr Murabahah Profit (reduce income), Cr VAT Output (2410)
                    $this->recordByCode([
                        'date' => $order->created_at ?? now(),
                        'reference' => ($order->reference ?: 'ORDER') . '-VAT',
                        'description' => 'VAT Output on store order ' . ($order->reference ?? $order->id),
                        'branch_id' => optional($order->user)->branch_id,
                        'external_key' => 'VAT:' . ($order->id ?? '0'),
                    ], [
                        ['code' => '4400', 'debit' => $taxAmount, 'description' => 'Reclassify VAT from Income'],
                        ['code' => '2410', 'credit' => $taxAmount, 'description' => 'VAT Output (Liability)'],
                    ]);
                }
            }
        } catch (\Throwable $e) {
            // Fail open: never block core order posting due to VAT logic
        }

        try {
            // Prefer inventory valuation if inventory tables exist; fallback to line_cost approach
            $branchId = optional($order->user)->branch_id;
            $order->loadMissing('items.product');
            $cogs = 0.0;

            if (\Illuminate\Support\Facades\Schema::hasTable('inventory_transactions')) {
                $inv = app(\App\Services\InventoryService::class);
                foreach ($order->items as $it) {
                    $prod = $it->product;
                    if ($prod && empty($it->vendor_id)) { // we own the stock
                        // Only track if product has track_stock column and flag true, otherwise treat as service/non-stock
                        $track = true;
                        if (\Illuminate\Support\Facades\Schema::hasColumn('products', 'track_stock')) {
                            $track = (bool) ($prod->track_stock);
                        }
                        if ($track) {
                            $cogs += $inv->issue($prod->id, (float) $it->quantity, $branchId, [
                                'reference_type' => 'store_orders',
                                'reference_id' => $order->id,
                                'performed_at' => $order->created_at ?? now(),
                            ]);
                        }
                    }
                }
            } elseif (\Illuminate\Support\Facades\Schema::hasColumn('products', 'track_stock')) {
                // Fallback: sum provided line_cost for tracked items
                foreach ($order->items as $it) {
                    $prod = $it->product;
                    if ($prod && $prod->track_stock && empty($it->vendor_id)) {
                        $cogs += (float) ($it->line_cost ?? 0);
                    }
                }
            }

            if ($cogs > 0.0) {
                $this->recordByCode([
                    'date' => $order->created_at ?? now(),
                    'reference' => ($order->reference ?: 'ORDER') . '-COGS',
                    'description' => 'COGS posting for store order ' . ($order->reference ?? $order->id),
                    'branch_id' => $branchId,
                    'external_key' => 'COGS:' . ($order->id ?? '0'),
                ], [
                    ['code' => '5100', 'debit' => $cogs, 'description' => 'Cost of Goods Sold'],
                    ['code' => '1200', 'credit' => $cogs, 'description' => 'Inventory'],
                ]);
            }
        } catch (\Throwable $e) {
            // Fail open: do not interrupt main flow
        }

        return $journal;
    }
    /**
     * Record Murabahah Financing (Receivable).
     * Dr Murabahah Receivables (1310), Cr Bank (1100), Cr Murabahah Profit (4400)
     */
    public function recordMurabahahFinancing(\App\Models\StoreOrder $order): LedgerJournal
    {
        return $this->recordByCode([
            'date' => now(),
            'reference' => 'MURABAHA-' . $order->id,
            'description' => "Murabahah Financing for order: {$order->reference}",
            'branch_id' => optional($order->user)->branch_id,
        ], [
            ['code' => '1310', 'debit' => $order->total_amount, 'description' => 'Receivable (Cost + Profit)'],
            ['code' => '1100', 'credit' => $order->total_cost, 'description' => 'Payment to Vendor/Inventory'],
            ['code' => '4400', 'credit' => $order->total_profit, 'description' => 'Murabahah Profit'],
        ]);
    }

    /**
     * Record Charity Receipt.
     * Dr Bank (1100), Cr Charity Fund (2220)
     */
    public function recordCharityReceipt(\App\Models\CharityEntry $charity): LedgerJournal
    {
        return $this->recordByCode([
            'date' => $charity->processed_at ?? now(),
            'reference' => 'CHARITY-' . $charity->id,
            'description' => "Charity Receipt: {$charity->source}",
            'branch_id' => $charity->branch_id ?? null,
        ], [
            ['code' => '1100', 'debit' => $charity->amount, 'description' => 'Bank Deposit'],
            ['code' => '2220', 'credit' => $charity->amount, 'description' => 'Charity Fund Credit'],
        ]);
    }

    /**
     * Record Project Profit Declaration.
     * Dr Bank (1100), Cr Management Fee (4500), Cr Profits Payable (2300)
     */
    public function recordProjectProfit(\App\Models\ProjectProfit $profit): LedgerJournal
    {
        return $this->recordByCode([
            'date' => $profit->created_at ?? now(),
            'reference' => 'PROFIT-DECL-' . $profit->id,
            'description' => "Profit Declaration for project: " . ($profit->project->name ?? 'Project #' . $profit->project_id),
            'branch_id' => $profit->branch_id ?? null,
        ], [
            ['code' => '1100', 'debit' => $profit->gross_profit, 'description' => 'Realized Profit (Bank)'],
            ['code' => '4500', 'credit' => $profit->management_fee_amount, 'description' => 'Management Fee Income'],
            ['code' => '2300', 'credit' => $profit->net_distributable, 'description' => 'Profits Distributable'],
        ]);
    }

    /**
     * Record Project Profit Payout.
     * Dr Profits Payable (2300), Cr Member Deposits (2200)
     */
    public function recordProjectProfitPayout(\App\Models\ProjectProfitPayout $payout): LedgerJournal
    {
        return $this->recordByCode([
            'date' => $payout->updated_at ?? now(),
            'reference' => 'PROFIT-PAY-' . $payout->id,
            'description' => "Profit Payout to Member: " . ($payout->user->name ?? 'User #' . $payout->user_id),
            'branch_id' => optional($payout->user)->branch_id,
        ], [
            ['code' => '2300', 'debit' => $payout->amount, 'description' => 'Profits Payable Debit'],
            ['code' => '2200', 'credit' => $payout->amount, 'description' => 'Member Wallet Credit'],
        ]);
    }

    /**
     * Record Sadaqah Contribution.
     * Dr Bank (1100), Cr Charity Fund (2220)
     */
    public function recordSadaqahContribution(\App\Models\SadaqahContribution $contribution): LedgerJournal
    {
        return $this->recordByCode([
            'date' => $contribution->created_at ?? now(),
            'reference' => $contribution->reference ?? 'SAD-' . $contribution->id,
            'description' => "Sadaqah Contribution for project: " . ($contribution->project->name ?? 'Project #' . $contribution->sadaqah_project_id),
            'branch_id' => $contribution->branch_id ?? null,
        ], [
            ['code' => '1100', 'debit' => $contribution->amount, 'description' => 'Bank Deposit'],
            ['code' => '2220', 'credit' => $contribution->amount, 'description' => 'Charity Fund Credit'],
        ]);
    }
}
