<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\YearEndCloseService;
use App\Services\MonthlyBalanceService;
use App\Services\FxRevaluationService;
use App\Services\FinancialReconciliationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;

class AccountingOpsController extends Controller
{
    public function yearEndClose(Request $request, YearEndCloseService $svc)
    {
        $this->authorize('ops.year_end_close');
        if (!Schema::hasTable('fiscal_periods')) {
            return response()->json(['message' => 'Migrations pending: fiscal_periods table missing'], 503);
        }
        $validated = $request->validate([
            'period_id' => 'required|integer',
            'dry_run' => 'boolean',
            'close' => 'boolean',
            'retained_code' => 'nullable|string',
        ]);
        $res = $svc->run((int)$validated['period_id'], $request->user()->id ?? null, (bool)($validated['dry_run'] ?? false), (bool)($validated['close'] ?? true), $validated['retained_code'] ?? null);
        return response()->json($res);
    }

    public function autoReverse(Request $request)
    {
        $this->authorize('ops.auto_reverse');
        if (!Schema::hasTable('ledger_journals')) {
            return response()->json(['message' => 'Migrations pending: ledger_journals table missing'], 503);
        }
        if (!Schema::hasColumn('ledger_journals', 'auto_reverse_on')) {
            return response()->json(['message' => 'Migrations pending: auto_reverse_on column missing'], 503);
        }
        // Dispatch the console command synchronously for simplicity
        $date = $request->input('date');
        $journal = $request->input('journal');
        $dry = $request->boolean('dry_run', false);
        Artisan::call('accounting:auto-reverse', array_filter([
            '--date' => $date,
            '--journal' => $journal,
            '--dry-run' => $dry ? true : null,
        ]));
        return response()->json(['output' => trim(Artisan::output())]);
    }

    public function rebuildMonthly(Request $request, MonthlyBalanceService $svc)
    {
        $this->authorize('ops.rebuild_monthly');
        if (!Schema::hasTable('ledger_account_monthly_balances')) {
            return response()->json(['message' => 'Migrations pending: monthly balances table missing'], 503);
        }
        $res = $svc->rebuild($request->input('from'), $request->input('to'), $request->input('branch_id'), $request->boolean('truncate', false));
        return response()->json($res);
    }

    public function fxRevalue(Request $request, FxRevaluationService $svc)
    {
        $this->authorize('ops.fx_revalue');
        if (!Schema::hasTable('fx_rates')) {
            return response()->json(['message' => 'Migrations pending: fx_rates table missing'], 503);
        }
        $date = $request->input('date') ?: now()->toDateString();
        $ccy = $request->input('currency');
        $dry = $request->boolean('dry_run', true);
        $res = $svc->run($date, $ccy, $request->user()->id ?? null, $dry);
        return response()->json($res);
    }

    public function reconcile(Request $request, FinancialReconciliationService $svc)
    {
        $this->authorize('ops.financial_reconcile');
        $fix = $request->boolean('fix', false);
        $userId = $request->input('user_id');

        $res = $svc->run($fix, $userId);

        return response()->json([
            'message' => $fix ? 'Financial reconciliation completed with fixes.' : 'Financial reconciliation report generated.',
            'data' => $res
        ]);
    }
}
