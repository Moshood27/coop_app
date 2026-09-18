<?php

namespace App\Console\Commands;

use App\Models\LedgerEntry;
use App\Models\LedgerJournal;
use Illuminate\Console\Command;

class VerifyTrialBalance extends Command
{
    protected $signature = 'ledger:verify {--from=} {--until=} {--details}';
    protected $description = 'Verify that total debits equal total credits, list unbalanced journals, and detect orphaned lines.';

    public function handle(): int
    {
        $from = $this->option('from');
        $until = $this->option('until');

        $query = LedgerJournal::query();
        if ($from) {
            $query->whereDate('date', '>=', $from);
        }
        if ($until) {
            $query->whereDate('date', '<=', $until);
        }

        $totalDebits = 0.0;
        $totalCredits = 0.0;
        $unbalanced = [];

        $query->chunkById(500, function ($journals) use (&$totalDebits, &$totalCredits, &$unbalanced) {
            foreach ($journals as $j) {
                $d = (float) $j->entries()->sum('debit');
                $c = (float) $j->entries()->sum('credit');
                $totalDebits += $d;
                $totalCredits += $c;
                if (round($d, 2) !== round($c, 2)) {
                    $unbalanced[] = [$j->id, $j->date?->toDateString(), $d, $c, $j->reference];
                }
            }
        });

        $this->line("Total Debits:  " . number_format($totalDebits, 2));
        $this->line("Total Credits: " . number_format($totalCredits, 2));

        if (round($totalDebits, 2) === round($totalCredits, 2)) {
            $this->info('Ledger is balanced overall.');
        } else {
            $this->error('Ledger is NOT balanced overall.');
        }

        if (!empty($unbalanced)) {
            $this->warn('Unbalanced journals:');
            foreach ($unbalanced as [$id, $date, $d, $c, $ref]) {
                $this->line(" - #$id [$date] DR=" . number_format($d, 2) . ' CR=' . number_format($c, 2) . " REF=$ref");
            }
        }

        // Orphaned lines
        $orphans = LedgerEntry::query()->whereDoesntHave('journal')->count();
        if ($orphans > 0) {
            $this->error("Found $orphans orphaned ledger lines (no parent journal).");
        } else {
            $this->info('No orphaned ledger lines found.');
        }

        return self::SUCCESS;
    }
}
