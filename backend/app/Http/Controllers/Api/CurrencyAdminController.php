<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use App\Services\CurrencyService;

class CurrencyAdminController extends Controller
{
    public function realized(Request $request, CurrencyService $currency)
    {
        $this->authorize('fx.realized_posting');
        if (!Schema::hasTable('ledger_journals') || !Schema::hasTable('ledger_entries')) {
            return response()->json([
                'message' => 'Ledger tables are missing. Run migrations after deployment.'
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $validated = $request->validate([
            'currency_code' => 'required|string|size:3',
            'amount_foreign' => 'required|numeric',
            'recognition_rate' => 'required|numeric',
            'settlement_rate' => 'required|numeric',
            'counter_account_code' => 'required|string',
            'fx_gain_account_code' => 'required|string',
            'fx_loss_account_code' => 'required|string',
            'branch_id' => 'nullable|integer',
            'reference' => 'nullable|string',
            'description' => 'nullable|string',
            'date' => 'nullable|date',
        ]);

        $journal = $currency->recordRealizedDifference(
            $validated['currency_code'],
            (float)$validated['amount_foreign'],
            (float)$validated['recognition_rate'],
            (float)$validated['settlement_rate'],
            $validated['counter_account_code'],
            $validated['fx_gain_account_code'],
            $validated['fx_loss_account_code'],
            [
                'branch_id' => $validated['branch_id'] ?? null,
                'reference' => $validated['reference'] ?? null,
                'description' => $validated['description'] ?? null,
                'date' => $validated['date'] ?? null,
            ]
        );

        return response()->json([
            'status' => $journal ? 'posted' : 'no-difference',
            'journal_id' => optional($journal)->id,
        ]);
    }
}
