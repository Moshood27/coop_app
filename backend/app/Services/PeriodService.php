<?php

namespace App\Services;

use App\Models\FiscalPeriod;
use Illuminate\Support\Facades\Schema;

class PeriodService
{
    public function isAvailable(): bool
    {
        return Schema::hasTable('fiscal_periods');
    }

    public function findForDate($date): ?FiscalPeriod
    {
        if (!$this->isAvailable()) return null;
        return FiscalPeriod::containingDate($date)->first();
    }

    public function isDateOpen($date): bool
    {
        if (!$this->isAvailable()) return true; // fail open before migration
        $period = $this->findForDate($date);
        return $period ? !$period->is_closed : true;
    }

    public function close(int $periodId, int $userId = null): ?FiscalPeriod
    {
        if (!$this->isAvailable()) return null;
        $period = FiscalPeriod::find($periodId);
        if ($period) {
            $period->is_closed = true;
            $period->closed_at = now();
            $period->closed_by = $userId;
            $period->save();
        }
        return $period;
    }

    public function open(int $periodId): ?FiscalPeriod
    {
        if (!$this->isAvailable()) return null;
        $period = FiscalPeriod::find($periodId);
        if ($period) {
            $period->is_closed = false;
            $period->closed_at = null;
            $period->closed_by = null;
            $period->save();
        }
        return $period;
    }
}
