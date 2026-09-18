<?php

namespace App\Services;

use App\Models\FixedAsset;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class FixedAssetService
{
    public function featureEnabled(): bool
    {
        return config('accounting.features.fixed_assets') && Schema::hasTable('fixed_assets');
    }

    public function calculateMonthlyDepreciation(FixedAsset $asset, Carbon $period): float
    {
        // Skip if disposed or before acquisition or beyond useful life
        if ($asset->is_disposed) {
            return 0.0;
        }
        $acq = $asset->acquisition_date ? Carbon::parse($asset->acquisition_date)->startOfDay() : null;
        if ($acq && $period->lt($acq->copy()->startOfMonth())) {
            return 0.0;
        }
        $life = max(1, (int)($asset->useful_life_months ?? 0));
        if ($life <= 0) {
            return 0.0;
        }
        $cost = (float)$asset->cost;
        $residual = (float)($asset->residual_value ?? 0.0);
        $base = max(0.0, $cost - $residual);

        $method = $asset->depreciation_method ?: 'straight_line';
        if ($method === 'declining_balance') {
            $rate = 2.0 / $life; // double-declining approx
            $bookValue = $this->estimateBookValue($asset, $period);
            $amount = round($bookValue * $rate / 12.0, 2);
            // Ensure we don't go below residual
            $remaining = max(0.0, ($bookValue - $amount) - $residual);
            if ($remaining < 0) {
                $amount = max(0.0, $bookValue - $residual);
            }
            return $amount;
        }

        // straight_line
        return round($base / $life, 2);
    }

    protected function estimateBookValue(FixedAsset $asset, Carbon $period): float
    {
        // Simple estimate: cost minus straight-line to date; for precise values, store accumulated depreciation in DB when available.
        $acq = $asset->acquisition_date ? Carbon::parse($asset->acquisition_date)->startOfMonth() : $period->copy()->startOfMonth();
        $monthsUsed = max(0, ($acq->diffInMonths($period->copy()->startOfMonth())));
        $life = max(1, (int)($asset->useful_life_months ?? 1));
        $slPerMonth = max(0.0, ((float)$asset->cost - (float)($asset->residual_value ?? 0)) / $life);
        $book = (float)$asset->cost - ($monthsUsed * $slPerMonth);
        return max($book, (float)($asset->residual_value ?? 0));
    }

    /**
     * Post monthly depreciation for all active assets.
     * Returns [countAssets, totalAmount, journalsCreated].
     * No-ops when feature/tables not enabled or required accounts not configured.
     */
    public function postMonthlyDepreciation(Carbon $period, bool $dryRun = false): array
    {
        if (!$this->featureEnabled()) {
            return [0, 0.0, 0];
        }
        $drAccount = config('accounting.accounts.depreciation_expense_account_id');
        $crAccount = config('accounting.accounts.accumulated_depreciation_account_id');
        if (!$drAccount || !$crAccount) {
            Log::warning('Depreciation accounts not configured; skipping posting.');
            return [0, 0.0, 0];
        }

        $count = 0; $total = 0.0; $journals = 0;
        $assets = FixedAsset::query()->where('is_disposed', false)->get();
        foreach ($assets as $asset) {
            $amount = $this->calculateMonthlyDepreciation($asset, $period);
            if ($amount <= 0) {
                continue;
            }

            $count++; $total += $amount;

            if ($dryRun) {
                continue;
            }

            // Post via LedgerService::recordSimple if available
            try {
                $desc = 'Depreciation - '.$asset->name.' - '.$period->format('M Y');
                /** @var \App\Services\LedgerService $ledger */
                $ledger = app(\App\Services\LedgerService::class);
                if (method_exists($ledger, 'recordSimple')) {
                    $journal = $ledger->recordSimple(
                        [
                            'date' => $period->copy()->endOfMonth()->toDateString(),
                            'description' => $desc,
                            'reference' => 'DEP-'.$period->format('Ym').'-'.$asset->id,
                        ],
                        (int) $drAccount,
                        (int) $crAccount,
                        (float) $amount
                    );
                    if ($journal && isset($asset->ledger_journal_id) && Schema::hasColumn('fixed_assets', 'ledger_journal_id')) {
                        $asset->ledger_journal_id = $journal->id;
                        $asset->save();
                    }
                    $journals++;
                }
            } catch (\Throwable $e) {
                Log::error('Failed posting depreciation', [
                    'asset_id' => $asset->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return [$count, round($total, 2), $journals];
    }
}
