<?php

namespace App\Services;

use App\Models\StoreOrder;
use App\Models\StoreOrderItem;
use App\Models\TaxRate;
use App\Models\TaxTransaction;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class TaxService
{
    public function isReady(): bool
    {
        try {
            return Schema::hasTable('tax_rates');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Compute VAT Output for a store order.
     * Assumptions:
     * - Prices are tax-inclusive by default unless `cooperative.tax_inclusive` (or setting) is false.
     * - Product-level tax mapping exists in `product_tax_rates` table (if present).
     * Returns [total_tax => float, lines => [[item_id, rate, base, tax], ...]]
     */
    public function computeOrderOutputTax(StoreOrder $order): array
    {
        if (!$this->isReady()) {
            return ['total_tax' => 0.0, 'lines' => []];
        }

        $inclusive = (bool) (config('cooperative.tax_inclusive', true));

        $order->loadMissing('items.product');

        $total = 0.0;
        $lines = [];
        foreach ($order->items as $it) {
            $rate = $this->getProductRatePercent((int) $it->product_id, $order->created_at);
            if ($rate <= 0) {
                continue;
            }
            $base = (float) ($it->line_total ?? ((float) $it->unit_price * (int) $it->quantity));
            if ($base <= 0) continue;

            if ($inclusive) {
                $tax = round($base - ($base / (1 + ($rate / 100))), 2);
            } else {
                $tax = round($base * ($rate / 100), 2);
            }

            if ($tax <= 0) continue;

            $total += $tax;
            $lines[] = [
                'order_item_id' => $it->id,
                'rate_percent' => $rate,
                'base_amount' => $base,
                'tax_amount' => $tax,
            ];
        }

        // Persist audit trail if table exists
        try {
            if (Schema::hasTable('tax_transactions') && $total > 0) {
                foreach ($lines as $ln) {
                    TaxTransaction::create([
                        'store_order_id' => $order->id,
                        'store_order_item_id' => $ln['order_item_id'] ?? null,
                        'direction' => 'output',
                        'rate_percent' => $ln['rate_percent'],
                        'base_amount' => $ln['base_amount'],
                        'tax_amount' => $ln['tax_amount'],
                    ]);
                }
            }
        } catch (\Throwable $e) {
            // Ignore persistence if schema not ready
        }

        return [
            'total_tax' => round($total, 2),
            'lines' => $lines,
        ];
    }

    /**
     * Get product tax rate (percent) effective at date.
     */
    public function getProductRatePercent(int $productId, $atDate = null): float
    {
        try {
            if (!Schema::hasTable('product_tax_rates')) return 0.0;

            $q = DB::table('product_tax_rates as ptr')
                ->join('tax_rates as tr', 'tr.id', '=', 'ptr.tax_rate_id')
                ->where('ptr.product_id', $productId)
                ->where('tr.is_active', true)
                ->orderByDesc('ptr.effective_from');

            if ($atDate) {
                $d = date('Y-m-d', strtotime((string) $atDate));
                $q->where(function ($w) use ($d) {
                    $w->whereNull('ptr.effective_from')->orWhere('ptr.effective_from', '<=', $d);
                });
                $q->where(function ($w) use ($d) {
                    $w->whereNull('ptr.effective_to')->orWhere('ptr.effective_to', '>=', $d);
                });
            }

            $row = $q->first(['tr.percent']);
            return (float) ($row->percent ?? 0.0);
        } catch (\Throwable $e) {
            return 0.0;
        }
    }
}
