<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GeneralLedgerReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class GeneralLedgerController extends Controller
{
    public function show($accountId, Request $request, GeneralLedgerReportService $service)
    {
        $this->authorize('accounting.view_gl');
        if (!Schema::hasTable('ledger_entries')) {
            abort(503, 'Migrations for ledger are not yet applied.');
        }

        $validated = $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'branch_id' => 'nullable|integer',
        ]);

        $branchId = $validated['branch_id'] ?? null;

        return $service->build((int)$accountId, $validated['from'], $validated['to'], $branchId);
    }
}
