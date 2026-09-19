<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BranchAccountingReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class BranchReportsController extends Controller
{
    /**
     * Return a trial balance for a branch within optional date range.
     */
    public function trialBalance(Request $request, int $branchId)
    {
        $this->authorize('accounting.view_branch_tb');
        if (!Schema::hasColumn('ledger_entries', 'branch_id')) {
            return response()->json([
                'message' => 'Branch segmentation is not enabled yet. Please run migrations after deployment.'
            ], 503);
        }

        $data = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        /** @var BranchAccountingReportService $svc */
        $svc = app(BranchAccountingReportService::class);
        $tb = $svc->buildTrialBalanceByBranch($branchId, $data['from'] ?? null, $data['to'] ?? null);
        return response()->json($tb);
    }
}
