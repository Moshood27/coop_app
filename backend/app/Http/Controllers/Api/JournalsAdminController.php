<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LedgerJournal;
use App\Services\JournalApprovalService;
use Illuminate\Support\Facades\Schema;

class JournalsAdminController extends Controller
{
    protected function guardMigrations()
    {
        if (!Schema::hasColumn('ledger_journals', 'status')) {
            abort(503, 'Migrations for journal approvals are not yet applied.');
        }
    }

    public function submit($id, JournalApprovalService $svc)
    {
        $this->guardMigrations();
        $journal = LedgerJournal::findOrFail($id);
        $this->authorize('submit', $journal);
        return $svc->submitForApproval($journal);
    }

    public function approve($id, JournalApprovalService $svc)
    {
        $this->guardMigrations();
        $journal = LedgerJournal::findOrFail($id);
        $this->authorize('approve', $journal);
        $userId = optional(request()->user())->id ?? 0;
        return $svc->approve($journal, $userId);
    }

    public function reject($id, JournalApprovalService $svc)
    {
        $this->guardMigrations();
        $journal = LedgerJournal::findOrFail($id);
        $this->authorize('reject', $journal);
        return $svc->reject($journal);
    }
}
