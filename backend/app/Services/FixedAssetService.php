<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetDepreciation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FixedAssetService
{
    public function createAsset(array $data, bool $schedule = true): Asset
    {
        return DB::transaction(function () use ($data, $schedule) {
            /** @var Asset $asset */
            $asset = Asset::create($data);

            if ($schedule) {
                $this->generateSchedules($asset);
            }

            return $asset;
        });
    }

    public function generateSchedules(Asset $asset): void
    {
        $category = $asset->category;
        if (!$category) {
            return;
        }

        // Straight-line only for now
        $life = max(1, (int) $category->useful_life_months);
        $base = (float) $asset->acquisition_cost - (float) $asset->residual_value;
        $perMonth = round($base / $life, 2);

        $start = Carbon::parse($asset->acquisition_date)->copy()->startOfMonth();
        for ($i = 0; $i < $life; $i++) {
            $pStart = $start->copy()->addMonths($i);
            $pEnd = $pStart->copy()->endOfMonth();

            AssetDepreciation::firstOrCreate([
                'asset_id' => $asset->id,
                'period_start' => $pStart->toDateString(),
                'period_end' => $pEnd->toDateString(),
            ], [
                'amount' => $perMonth,
                'status' => 'scheduled',
            ]);
        }
    }

    public function postDepreciation(AssetDepreciation $dep): ?\App\Models\LedgerJournal
    {
        $asset = $dep->asset;
        $category = $asset->category;
        if (!$category || $dep->status === 'posted') {
            return $dep->journal; // nothing to do
        }

        $assetAccountId = $asset->asset_account_id ?: $category->asset_account_id;
        $accumAccountId = $asset->accum_dep_account_id ?: $category->accum_dep_account_id;
        $expenseAccountId = $asset->dep_expense_account_id ?: $category->dep_expense_account_id;

        // Post: Dr Depreciation Expense, Cr Accumulated Depreciation
        $journal = app(LedgerService::class)->record([
            'date' => $dep->period_end,
            'reference' => 'DEP-' . $asset->id . '-' . $dep->period_end,
            'description' => 'Monthly depreciation for asset #' . $asset->id . ' (' . $asset->name . ')',
            'branch_id' => $asset->branch_id,
        ], [
            ['ledger_account_id' => $expenseAccountId, 'debit' => $dep->amount, 'description' => 'Depreciation Expense'],
            ['ledger_account_id' => $accumAccountId, 'credit' => $dep->amount, 'description' => 'Accumulated Depreciation'],
        ]);

        $dep->update([
            'ledger_journal_id' => $journal->id,
            'posted_at' => now(),
            'status' => 'posted',
        ]);

        return $journal;
    }
}
