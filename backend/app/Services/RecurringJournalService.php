<?php

namespace App\Services;

use App\Models\RecurringJournal;
use Illuminate\Support\Facades\Schema;

class RecurringJournalService
{
    public function isAvailable(): bool
    {
        return Schema::hasTable('recurring_journals');
    }

    /**
     * Generate and post a journal from a recurring template for the provided execution date.
     * Fails open (no-op) if migrations are not yet applied.
     */
    public function runNow(RecurringJournal $recurring, $runDate = null): ?\App\Models\LedgerJournal
    {
        if (!$this->isAvailable()) return null;

        $template = (array) $recurring->template;
        if (empty($template)) return null;

        $runDate = $runDate ?: now();

        $entries = [];
        foreach ($template as $line) {
            $entries[] = [
                'ledger_account_id' => $line['ledger_account_id'],
                'debit' => $line['debit'] ?? 0,
                'credit' => $line['credit'] ?? 0,
                'description' => $line['description'] ?? null,
                'branch_id' => $line['branch_id'] ?? null,
            ];
        }

        $externalKey = sprintf('RJ-%d-%s', $recurring->id, date('Ymd', strtotime((string)$runDate)));

        $journal = app(LedgerService::class)->record([
            'date' => $runDate,
            'reference' => $externalKey,
            'external_key' => $externalKey,
            'description' => $recurring->description ?: $recurring->name,
            'created_by' => $recurring->created_by,
        ], $entries);

        // Update last/next run timestamps (best effort; guard for schema presence)
        try {
            $recurring->last_run_at = now();
            // naive monthly schedule: if schedule contains 'monthly', add 1 month; otherwise null
            if ($recurring->schedule && stripos($recurring->schedule, 'monthly') !== false) {
                $recurring->next_run_at = now()->addMonth();
            }
            $recurring->save();
        } catch (\Throwable $e) {
            // ignore
        }

        return $journal;
    }
}
