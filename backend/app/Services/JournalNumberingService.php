<?php

namespace App\Services;

use App\Models\LedgerJournal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class JournalNumberingService
{
    public function featureEnabled(): bool
    {
        return config('accounting.features.journal_numbering') && Schema::hasColumn('ledger_journals', 'number');
    }

    public function assignNumber(LedgerJournal $journal): ?string
    {
        if (!$this->featureEnabled()) {
            return null;
        }
        if (isset($journal->number) && $journal->number) {
            return $journal->number;
        }
        $date = $journal->date ?: now();
        $prefix = $date->format('Ym'); // e.g., 202609
        $max = DB::table('ledger_journals')
            ->whereNotNull('number')
            ->where('number', 'like', $prefix.'-%')
            ->max('number');

        $next = 1;
        if ($max) {
            $parts = explode('-', $max);
            $last = (int)($parts[1] ?? 0);
            $next = $last + 1;
        }
        $num = sprintf('%s-%04d', $prefix, $next);
        $journal->number = $num;
        $journal->save();
        return $num;
    }
}
