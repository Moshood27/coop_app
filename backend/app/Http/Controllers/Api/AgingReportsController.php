<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use App\Services\AgingReportService;

class AgingReportsController extends Controller
{
    public function index(Request $request, AgingReportService $service)
    {
        $this->authorize('reports.aging');
        // Guard pre-migration environments
        if (!Schema::hasTable('ledger_entries') || !Schema::hasTable('ledger_accounts') || !Schema::hasTable('ledger_journals')) {
            return response()->json([
                'message' => 'Accounting migrations pending. Run php artisan migrate after deployment to enable Aging Reports.'
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $type = in_array($request->query('type'), ['ar', 'ap']) ? $request->query('type') : 'ar';
        $asOf = $request->query('asOf', now()->toDateString());
        $buckets = array_filter(explode(',', (string)$request->query('buckets', '30,60,90')));
        $buckets = array_map('intval', $buckets);
        $branchId = $request->query('branch_id');
        $codes = $request->query('accounts');
        $codes = $codes ? array_filter(explode(',', (string)$codes)) : null;

        $aging = $service->build($type, $asOf, $buckets ?: [30,60,90], $branchId ? (int)$branchId : null, $codes);

        if ($request->query('format') === 'csv') {
            $csv = $service->toCsv($aging);
            $filename = strtoupper($type) . '-AGING-' . $asOf . '.csv';
            return response($csv)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
        }

        return response()->json($aging);
    }
}
