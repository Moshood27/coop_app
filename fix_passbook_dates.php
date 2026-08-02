<?php

use App\Models\Contribution;
use App\Models\TakafulContribution;
use App\Models\TakafulPoolEntry;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Script to fix missing paid_at dates and correct migrated record dates.
 * Run via: php artisan tinker fix_passbook_dates.php
 */

echo "Starting database correction...\n";

// 1. Fix missing paid_at for all successful contributions
$affected = DB::table('contributions')
    ->where('status', 'success')
    ->whereNull('paid_at')
    ->update(['paid_at' => DB::raw('created_at')]);

echo "Updated {$affected} records setting paid_at = created_at.\n";

// 2. Try to recover dates for migrated records
// Migrated records have references like 'MIG-REC-...'
// Unfortunately, if created_at was ignored during import, they all have the import timestamp.
// However, TakafulContribution records HAVE a 'period' column (e.g. '2026-01') which was correctly saved.
// We can use this to restore dates for Takaful contributions at least.

$takafulFixes = 0;
TakafulContribution::where('reference', 'like', 'MIG-REC-TAKF-%')->chunk(100, function ($records) use (&$takafulFixes) {
    foreach ($records as $record) {
        if ($record->period) {
            $originalDate = $record->created_at;
            $newDate = Carbon::parse($record->period . '-01 12:00:00');
            
            // Fix corresponding Contribution record first using the original created_at
            $contrib = Contribution::where('user_id', $record->user_id)
                ->where('amount', $record->amount)
                ->where('reference', 'like', 'MIG-REC-TAK-%')
                ->whereBetween('created_at', [
                    (clone $originalDate)->subMinutes(5),
                    (clone $originalDate)->addMinutes(5)
                ])
                ->first();
            
            if ($contrib) {
                $contrib->paid_at = $newDate;
                $contrib->created_at = $newDate;
                $contrib->save(['timestamps' => false]);
                $takafulFixes++;
            }

            // Fix the TakafulContribution itself
            $record->created_at = $newDate;
            $record->save(['timestamps' => false]);
        }
    }
});

echo "Recovered {$takafulFixes} Takaful contribution dates using 'period' column.\n";

// 3. Fix TakafulPoolEntry dates as well
$poolFixes = DB::table('takaful_pool_entries')
    ->where('reference', 'like', 'MIG-REC-POOL-%')
    ->update(['created_at' => DB::raw('(SELECT created_at FROM takaful_contributions WHERE takaful_contributions.user_id = takaful_pool_entries.user_id AND takaful_contributions.amount = takaful_pool_entries.amount AND takaful_contributions.reference LIKE "MIG-REC-TAKF-%" LIMIT 1)')]);

echo "Finished correction script.\n";
