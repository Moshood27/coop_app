<?php

namespace App\Services;

use App\Models\LedgerJournal;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Exception;

class JournalApprovalService
{
    const STATUS_DRAFT = 'draft';
    const STATUS_PENDING = 'pending_approval';
    const STATUS_APPROVED = 'approved';
    const STATUS_POSTED = 'posted';
    const STATUS_REJECTED = 'rejected';

    public function supports(): bool
    {
        return Schema::hasColumn('ledger_journals', 'status');
    }

    public function submitForApproval(LedgerJournal $journal): LedgerJournal
    {
        if (!$this->supports()) return $journal; // no-op before migration
        $journal->status = self::STATUS_PENDING;
        $journal->save();
        return $journal;
    }

    public function approve(LedgerJournal $journal, int $approverId): LedgerJournal
    {
        if (!$this->supports()) return $journal; // no-op
        if (!$journal->isBalanced()) {
            throw new Exception('Cannot approve unbalanced journal.');
        }
        DB::transaction(function () use ($journal, $approverId) {
            $journal->status = self::STATUS_APPROVED;
            $journal->approved_by = $approverId;
            $journal->approved_at = now();
            $journal->posted_at = now();
            $journal->save();
        });
        return $journal;
    }

    public function reject(LedgerJournal $journal): LedgerJournal
    {
        if (!$this->supports()) return $journal; // no-op
        $journal->status = self::STATUS_REJECTED;
        $journal->save();
        return $journal;
    }
}
