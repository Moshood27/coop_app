<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FxRevaluationService
{
    public function isAvailable(): bool
    {
        return Schema::hasTable('fx_rates')
            && Schema::hasTable('ledger_accounts')
            && Schema::hasTable('ledger_entries')
            && Schema::hasTable('ledger_journals');
    }

    /**
     * Scaffold for FX revaluation.
     * Calculates adjustments for monetary accounts in foreign currency and, if enabled,
     * posts revaluation journals (reversing next day). Currently a minimal skeleton
     * that only operates when currency schema exists.
     */
    public function run(string $valuationDate, ?string $currency = null, ?int $userId = null, bool $dryRun = true): array
    {
        if (!$this->isAvailable()) {
            return ['created' => false, 'reason' => 'Currency schema not ready'];
        }

        // If ledger_journals lacks currency_code/fx_rate, return early
        if (!Schema::hasColumn('ledger_journals', 'currency_code') || !Schema::hasColumn('ledger_journals', 'fx_rate')) {
            return ['created' => false, 'reason' => 'Ledger journals have no currency columns'];
        }

        // This is a placeholder that returns success with no postings until full multi-currency is enabled.
        // It can be expanded later to compute balances by (account,currency) and post adjustments.
        return [
            'created' => false,
            'reason' => 'FX revaluation scaffold in place; enable after multi-currency rollout',
            'valuation_date' => $valuationDate,
            'currency' => $currency,
            'dry_run' => $dryRun,
        ];
    }
}
