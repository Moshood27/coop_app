<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TaxRate;
use App\Models\Product;
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TaxAdminController extends Controller
{
    protected function guardSchema()
    {
        if (!Schema::hasTable('tax_rates')) {
            abort(503, 'Tax/VAT migrations are not yet applied. Please run php artisan migrate on the server.');
        }
    }

    public function listRates(Request $request)
    {
        $this->guardSchema();
        $this->authorize('viewAny', \App\Models\TaxRate::class);
        return response()->json(TaxRate::orderBy('name')->get());
    }

    public function createRate(Request $request)
    {
        $this->guardSchema();
        $this->authorize('create', \App\Models\TaxRate::class);
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'code' => 'required|string|max:20|unique:tax_rates,code',
            'percent' => 'required|numeric|min:0|max:100',
            'country' => 'nullable|string|max:2',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'is_active' => 'nullable|boolean',
        ]);
        $rate = TaxRate::create($data);
        return response()->json($rate, 201);
    }

    public function updateRate(Request $request, $id)
    {
        $this->guardSchema();
        $rate = TaxRate::findOrFail($id);
        $this->authorize('update', $rate);
        $data = $request->validate([
            'name' => 'sometimes|string|max:120',
            'code' => 'sometimes|string|max:20|unique:tax_rates,code,' . $rate->id,
            'percent' => 'sometimes|numeric|min:0|max:100',
            'country' => 'nullable|string|max:2',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'is_active' => 'nullable|boolean',
        ]);
        $rate->update($data);
        return response()->json($rate);
    }

    public function deleteRate($id)
    {
        $this->guardSchema();
        $rate = TaxRate::findOrFail($id);
        $this->authorize('delete', $rate);
        $rate->delete();
        return response()->json(['deleted' => true]);
    }

    public function mapProductRate(Request $request, $productId)
    {
        $this->guardSchema();
        $this->authorize('tax.map_products');
        $data = $request->validate([
            'tax_rate_id' => 'required|integer|exists:tax_rates,id',
            'effective_from' => 'nullable|date',
        ]);

        $product = Product::findOrFail($productId);
        DB::table('product_tax_rates')->insert([
            'product_id' => $product->id,
            'tax_rate_id' => (int) $data['tax_rate_id'],
            'effective_from' => $data['effective_from'] ?? now()->toDateString(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['mapped' => true]);
    }

    /**
     * Run a simple VAT settlement: offset VAT Input against VAT Output by the smaller amount.
     * Posts a journal Dr VAT Output, Cr VAT Input for the offset amount.
     */
    public function runSettlement(Request $request, LedgerService $ledger)
    {
        $this->guardSchema();
        $this->authorize('tax.run_settlement');

        // Guard presence of ledger accounts
        $vatOutput = DB::table('ledger_accounts')->where('code', '2410')->first();
        $vatInput = DB::table('ledger_accounts')->where('code', '1410')->first();
        if (!$vatOutput || !$vatInput) {
            abort(422, 'VAT ledger accounts not found (codes 2410 and 1410). Apply migrations.');
        }

        // Current balances (all-time). For period-specific, a more advanced report is needed.
        $outputBal = (float) (app(\App\Models\LedgerAccount::class)::where('code', '2410')->first()?->balance ?? 0);
        $inputBal = (float) (app(\App\Models\LedgerAccount::class)::where('code', '1410')->first()?->balance ?? 0);

        // Liability accounts usually have credit balances; asset accounts debit balances. Use absolute values.
        $outputCredit = $outputBal < 0 ? -$outputBal : $outputBal; // assume balance accessor returns signed appropriately
        $inputDebit = $inputBal < 0 ? -$inputBal : $inputBal;

        $offset = min($outputCredit, $inputDebit);
        if ($offset <= 0) {
            return response()->json([
                'message' => 'No settlement necessary',
                'output_balance' => round($outputCredit, 2),
                'input_balance' => round($inputDebit, 2),
                'offset' => 0,
            ]);
        }

        $ref = 'VAT-SETTLE-' . now()->format('Ymd-His');
        $journal = $ledger->recordByCode([
            'date' => now(),
            'reference' => $ref,
            'description' => 'VAT settlement (offset input against output)',
        ], [
            ['code' => '2410', 'debit' => $offset, 'description' => 'Reduce VAT Output liability'],
            ['code' => '1410', 'credit' => $offset, 'description' => 'Clear VAT Input asset'],
        ]);

        return response()->json([
            'message' => 'Settlement posted',
            'offset' => round($offset, 2),
            'journal_id' => $journal->id,
        ], 201);
    }
}
