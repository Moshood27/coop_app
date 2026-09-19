<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankStatement;
use App\Services\BankReconciliationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class BankReconciliationController extends Controller
{
    /**
     * List active bank accounts for selection in admin UI.
     * Guarded to avoid failures before migrations are applied.
     */
    public function listBankAccounts(Request $request)
    {
        $this->authorize('viewAny', \App\Models\BankAccount::class);
        if (!Schema::hasTable('bank_accounts')) {
            return response()->json([
                'message' => 'Bank reconciliation feature is not enabled yet. Please run migrations after deployment.'
            ], 503);
        }

        $q = BankAccount::query()->where('is_active', true);
        // Optional: filter by branch
        if ($request->filled('branch_id')) {
            $q->where('branch_id', (int)$request->input('branch_id'));
        }

        $accounts = $q
            ->orderBy('name')
            ->get(['id', 'name', 'account_number', 'branch_id', 'ledger_account_id', 'is_active']);

        return response()->json($accounts);
    }
    /**
     * Create a bank statement record for a bank account and optional period balances.
     * Guarded to avoid failures before migrations are applied.
     */
    public function createStatement(Request $request)
    {
        $this->authorize('create', \App\Models\BankStatement::class);
        if (!Schema::hasTable('bank_statements') || !Schema::hasTable('bank_accounts')) {
            return response()->json([
                'message' => 'Bank reconciliation feature is not enabled yet. Please run migrations after deployment.'
            ], 503);
        }

        $data = $request->validate([
            'bank_account_id' => 'required|integer|exists:bank_accounts,id',
            'period_from' => 'required|date',
            'period_to' => 'required|date|after_or_equal:period_from',
            'opening_balance' => 'nullable|numeric',
            'closing_balance' => 'nullable|numeric',
        ]);

        $stmt = BankStatement::create([
            'bank_account_id' => $data['bank_account_id'],
            'period_from' => $data['period_from'],
            'period_to' => $data['period_to'],
            'opening_balance' => (float)($data['opening_balance'] ?? 0),
            'closing_balance' => (float)($data['closing_balance'] ?? 0),
            'uploaded_by' => optional($request->user())->id,
            'status' => 'draft',
        ]);

        return response()->json($stmt, 201);
    }

    /**
     * Import statement lines. Accepts an array of rows under key `lines`.
     * Each row: {date, amount, description?, reference?}
     */
    public function importLines(Request $request, int $statementId)
    {
        if (!Schema::hasTable('bank_statement_lines')) {
            return response()->json([
                'message' => 'Bank reconciliation feature is not enabled yet. Please run migrations after deployment.'
            ], 503);
        }

        $statement = BankStatement::findOrFail($statementId);
        $this->authorize('import', $statement);

        $payload = $request->validate([
            'lines' => 'required|array|min:1',
            'lines.*.date' => 'nullable|string',
            'lines.*.amount' => 'required|numeric',
            'lines.*.description' => 'nullable|string',
            'lines.*.reference' => 'nullable|string',
        ]);

        /** @var BankReconciliationService $svc */
        $svc = app(BankReconciliationService::class);
        $count = $svc->importLines($statement, $payload['lines']);
        return response()->json(['imported' => $count]);
    }

    /**
     * Auto-match unmatched lines to ledger entries within tolerance.
     */
    public function autoMatch(Request $request, int $statementId)
    {
        if (!Schema::hasTable('bank_statement_lines')) {
            return response()->json([
                'message' => 'Bank reconciliation feature is not enabled yet. Please run migrations after deployment.'
            ], 503);
        }

        $statement = BankStatement::findOrFail($statementId);
        $this->authorize('automatch', $statement);
        $days = (int) $request->input('days_tolerance', 7);
        /** @var BankReconciliationService $svc */
        $svc = app(BankReconciliationService::class);
        $matched = $svc->autoMatch($statement, $days);
        return response()->json(['matched' => $matched]);
    }

    /**
     * Start a reconciliation session for a statement period.
     */
    public function startReconciliation(Request $request)
    {
        $this->authorize('start', \App\Models\BankReconciliation::class);
        if (!Schema::hasTable('bank_reconciliations')) {
            return response()->json([
                'message' => 'Bank reconciliation feature is not enabled yet. Please run migrations after deployment.'
            ], 503);
        }

        $data = $request->validate([
            'bank_account_id' => 'required|integer|exists:bank_accounts,id',
            'period_from' => 'required|date',
            'period_to' => 'required|date|after_or_equal:period_from',
            'ending_balance' => 'required|numeric',
        ]);

        $account = BankAccount::findOrFail($data['bank_account_id']);
        /** @var BankReconciliationService $svc */
        $svc = app(BankReconciliationService::class);
        $rec = $svc->startReconciliation($account, $data['period_from'], $data['period_to'], (float) $data['ending_balance'], optional($request->user())->id);
        return response()->json($rec, 201);
    }

    /**
     * Finalize a reconciliation.
     */
    public function finalize(int $id)
    {
        if (!Schema::hasTable('bank_reconciliations')) {
            return response()->json([
                'message' => 'Bank reconciliation feature is not enabled yet. Please run migrations after deployment.'
            ], 503);
        }

        $rec = BankReconciliation::findOrFail($id);
        $this->authorize('finalize', $rec);
        /** @var BankReconciliationService $svc */
        $svc = app(BankReconciliationService::class);
        $rec = $svc->finalize($rec);
        return response()->json($rec);
    }
}
