<?php

namespace App\Services;

use App\Support\Accounting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CurrencyService
{
    public function getRate(string $from, string $to, string|null $onDate = null): float
    {
        $from = strtoupper(trim($from));
        $to = strtoupper(trim($to));

        // Same currency
        if ($from === $to) {
            return 1.0;
        }

        // Only attempt lookup if schema exists or flagged as applied
        $hasTable = Accounting::tableExists('exchange_rates');
        if (!$hasTable && !Accounting::migrationApplied('multicurrency')) {
            return 1.0; // fallback
        }

        $date = $onDate ? Carbon::parse($onDate)->toDateString() : now()->toDateString();

        $row = DB::table('exchange_rates')
            ->where('base_currency', $from)
            ->where('quote_currency', $to)
            ->whereDate('rate_date', '<=', $date)
            ->orderByDesc('rate_date')
            ->first();

        return $row ? (float) $row->rate : 1.0;
    }

    public function convert(float $amount, string $from, string $to, string|null $onDate = null): float
    {
        $rate = $this->getRate($from, $to, $onDate);
        return round($amount * $rate, 2);
    }
}
