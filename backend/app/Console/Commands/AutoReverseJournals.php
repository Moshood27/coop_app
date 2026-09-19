<?php

namespace App\Console\Commands;

use App\Models\LedgerJournal;
use App\Services\LedgerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class AutoReverseJournals extends Command
{
    protected $signature = 'accounting:auto-reverse {--date=} {--limit=50} {--journal=} {--dry-run}';
    protected $description = 'Create reversing journals for items flagged with auto_reverse_on or for a specific journal.';

    public function handle(LedgerService $ledger): int
    {
        if (!Schema::hasTable('ledger_journals')) {
            $this->warn('Ledger journals table not available.');
            return self::SUCCESS;
        }

        $targetDate = $this->option('date') ?: now()->toDateString();
        $limit = (int) $this->option('limit');
        $dry = (bool) $this->option('dry-run');
        $oneId = $this->option('journal');

        $query = LedgerJournal::query()->with('entries');
        if ($oneId) {
            $query->where('id', $oneId);
        } else {
            if (!Schema::hasColumn('ledger_journals', 'auto_reverse_on')) {
                $this->warn('auto_reverse_on column not present; nothing to do.');
                return self::SUCCESS;
            }
            $query->whereNull('reversing_journal_id')
                ->whereNotNull('auto_reverse_on')
                ->where('auto_reverse_on', '<=', $targetDate)
                ->orderBy('auto_reverse_on');
        }

        $count = 0;
        foreach ($query->limit($limit)->get() as $journal) {
            $revDate = $oneId ? ($this->option('date') ?: now()->toDateString()) : ($journal->auto_reverse_on ?? $targetDate);
            $entries = [];
            foreach ($journal->entries as $e) {
                $entries[] = [
                    'ledger_account_id' => $e->ledger_account_id,
                    'debit' => $e->credit,
                    'credit' => $e->debit,
                    'description' => 'Reversal of entry #' . $e->id,
                    'branch_id' => $e->branch_id,
                ];
            }

            if ($dry) {
                $this->line("Would reverse journal #{$journal->id} on {$revDate} with " . count($entries) . ' lines.');
                $count++;
                continue;
            }

            $rev = $ledger->record([
                'date' => $revDate,
                'reference' => 'REV-' . ($journal->number ?? $journal->id),
                'description' => 'Reversal of journal #' . ($journal->number ?? $journal->id),
                'created_by' => auth()->id() ?? null,
            ], $entries);

            if (Schema::hasColumn('ledger_journals', 'reversing_journal_id')) {
                $journal->reversing_journal_id = $rev->id;
                $journal->save();
            }

            $this->info('Reversed journal #' . $journal->id . ' with new journal #' . $rev->id);
            $count++;
        }

        $this->info("Processed {$count} journal(s).");
        return self::SUCCESS;
    }
}
