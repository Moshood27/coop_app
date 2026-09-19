<?php

namespace App\Services;

use App\Models\FxRate;
use Illuminate\Support\Facades\Schema;

class CurrencyService
{
    protected string $baseCurrency;

    public function __construct(?string $baseCurrency = null)
    {
        $this->baseCurrency = $baseCurrency ?: (config('app.currency_base') ?? 'NGN');
    }

    public function getRate(string $currencyCode, $date = null): float
    {
        $currencyCode = strtoupper($currencyCode);
        if ($currencyCode === strtoupper($this->baseCurrency)) {
            return 1.0;
        }
        if (!Schema::hasTable('fx_rates')) {
            return 1.0; // fail open before migration
        }
        $date = $date ? date('Y-m-d', strtotime((string) $date)) : date('Y-m-d');
        $rate = FxRate::where('currency_code', $currencyCode)
            ->where('rate_date', '<=', $date)
            ->orderBy('rate_date', 'desc')
            ->first();
        return $rate ? (float) $rate->rate_to_base : 1.0;
    }

    public function convertToBase(float $amount, string $currencyCode, $date = null): float
    {
        return $amount * $this->getRate($currencyCode, $date);
    }

    /**
     * Record realized FX difference for a foreign-currency settlement.
     * Example: settlement of a receivable/payable recognized earlier at a different rate.
     * Posts difference to provided gain/loss accounts and offsets to the given counter account (e.g., AR/AP or Bank).
     *
     * This is intentionally generic and guarded by caller.
     */
    public function recordRealizedDifference(
        string $currencyCode,
        float $amountForeign,
        float $recognitionRate,
        float $settlementRate,
        string $counterAccountCode,
        string $fxGainAccountCode,
        string $fxLossAccountCode,
        array $meta = []
    ): ?\App\Models\LedgerJournal {
        $baseAtRecognition = $amountForeign * $recognitionRate;
        $baseAtSettlement = $amountForeign * $settlementRate;
        $diff = $baseAtSettlement - $baseAtRecognition; // positive = loss if paying more, but depends on context

        if (abs($diff) < 0.00001) {
            return null; // nothing to post
        }

        $data = [
            'date' => $meta['date'] ?? now(),
            'reference' => $meta['reference'] ?? 'FX-REALIZED',
            'description' => $meta['description'] ?? ('Realized FX on ' . strtoupper($currencyCode) . ' ' . number_format($amountForeign, 2)),
            'branch_id' => $meta['branch_id'] ?? null,
            'external_key' => $meta['external_key'] ?? null,
            'currency_code' => $currencyCode,
            'fx_rate' => $settlementRate,
        ];

        $entries = [];
        if ($diff > 0) {
            // Base increased at settlement: treat as FX Loss (expense)
            $entries[] = ['code' => $fxLossAccountCode, 'debit' => abs($diff), 'description' => 'Realized FX Loss'];
            $entries[] = ['code' => $counterAccountCode, 'credit' => abs($diff), 'description' => 'Counter'];
        } else {
            // Base decreased: FX Gain (income)
            $entries[] = ['code' => $counterAccountCode, 'debit' => abs($diff), 'description' => 'Counter'];
            $entries[] = ['code' => $fxGainAccountCode, 'credit' => abs($diff), 'description' => 'Realized FX Gain'];
        }

        return app(LedgerService::class)->recordByCode($data, $entries);
    }
}
