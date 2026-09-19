<?php

namespace App\Services;

use App\Models\InventoryTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class InventoryService
{
    public function isReady(): bool
    {
        return Schema::hasTable('inventory_transactions');
    }

    public function recordReceipt(int $productId, float $qty, float $unitCost, ?int $branchId = null, ?array $meta = []): ?InventoryTransaction
    {
        if (!$this->isReady()) return null;
        return InventoryTransaction::create([
            'product_id' => $productId,
            'branch_id' => $branchId,
            'type' => 'receipt',
            'qty' => $qty,
            'unit_cost' => $unitCost,
            'total_cost' => $qty * $unitCost,
            'reference_type' => $meta['reference_type'] ?? null,
            'reference_id' => $meta['reference_id'] ?? null,
            'ledger_journal_id' => $meta['ledger_journal_id'] ?? null,
            'performed_at' => Carbon::parse($meta['performed_at'] ?? now()),
            'created_by' => $meta['created_by'] ?? null,
        ]);
    }

    public function recordAdjustment(int $productId, float $qtyDelta, ?float $unitCost = null, ?int $branchId = null, ?array $meta = []): ?InventoryTransaction
    {
        if (!$this->isReady()) return null;
        // For positive qtyDelta, unitCost is required; for negative, use current average cost
        $avg = $this->getAverageCost($productId, $branchId);
        $unit = $unitCost ?? $avg;
        return InventoryTransaction::create([
            'product_id' => $productId,
            'branch_id' => $branchId,
            'type' => 'adjustment',
            'qty' => $qtyDelta,
            'unit_cost' => $unit,
            'total_cost' => $unit * $qtyDelta,
            'reference_type' => $meta['reference_type'] ?? null,
            'reference_id' => $meta['reference_id'] ?? null,
            'ledger_journal_id' => $meta['ledger_journal_id'] ?? null,
            'performed_at' => Carbon::parse($meta['performed_at'] ?? now()),
            'created_by' => $meta['created_by'] ?? null,
        ]);
    }

    /**
     * Issue stock (e.g., for a sale). Returns total COGS based on weighted-average cost.
     */
    public function issue(int $productId, float $qty, ?int $branchId = null, ?array $meta = []): float
    {
        if (!$this->isReady()) return 0.0;
        $avg = $this->getAverageCost($productId, $branchId);
        $total = $avg * $qty;
        InventoryTransaction::create([
            'product_id' => $productId,
            'branch_id' => $branchId,
            'type' => 'issue',
            'qty' => -abs($qty),
            'unit_cost' => $avg,
            'total_cost' => -abs($total),
            'reference_type' => $meta['reference_type'] ?? null,
            'reference_id' => $meta['reference_id'] ?? null,
            'ledger_journal_id' => $meta['ledger_journal_id'] ?? null,
            'performed_at' => Carbon::parse($meta['performed_at'] ?? now()),
            'created_by' => $meta['created_by'] ?? null,
        ]);
        return $total;
    }

    public function getStockOnHand(int $productId, ?int $branchId = null): float
    {
        if (!$this->isReady()) return 0.0;
        $q = InventoryTransaction::query()->where('product_id', $productId);
        if ($branchId) $q->where('branch_id', $branchId);
        return (float) $q->sum('qty');
    }

    public function getAverageCost(int $productId, ?int $branchId = null): float
    {
        if (!$this->isReady()) return 0.0;
        $q = InventoryTransaction::query()->where('product_id', $productId);
        if ($branchId) $q->where('branch_id', $branchId);
        $receiptsCost = (float) $q->whereIn('type', ['receipt', 'adjustment'])->sum(DB::raw('total_cost'));
        $issuesCost = (float) InventoryTransaction::query()
            ->where('product_id', $productId)
            ->when($branchId, fn($qq) => $qq->where('branch_id', $branchId))
            ->where('type', 'issue')
            ->sum(DB::raw('ABS(total_cost)'));
        $costInStock = $receiptsCost - $issuesCost;
        $stockQty = $this->getStockOnHand($productId, $branchId);
        if ($stockQty <= 0.000001) return 0.0;
        return $costInStock / $stockQty;
    }
}
