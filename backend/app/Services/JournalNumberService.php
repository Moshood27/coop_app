<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class JournalNumberService
{
    /**
     * Generate the next journal number in a year-based sequence.
     * Format: JV-YYYY-000001
     */
    public function nextNumber(?int $year = null): string
    {
        $year = $year ?: (int) now()->year;

        return DB::transaction(function () use ($year) {
            $row = DB::table('journal_sequences')->lockForUpdate()->where('year', $year)->first();
            if (!$row) {
                DB::table('journal_sequences')->insert([
                    'year' => $year,
                    'last_number' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $last = 0;
            } else {
                $last = (int) $row->last_number;
            }

            $next = $last + 1;
            DB::table('journal_sequences')->where('year', $year)->update([
                'last_number' => $next,
                'updated_at' => now(),
            ]);

            return sprintf('JV-%d-%06d', $year, $next);
        });
    }
}
