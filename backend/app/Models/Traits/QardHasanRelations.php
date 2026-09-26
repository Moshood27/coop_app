<?php

namespace App\Models\Traits;

use App\Models\User;
use App\Models\QardHasanRepayment;
use App\Models\TransactionApproval;
use App\Models\LedgerJournal;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait QardHasanRelations
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function repayments()
    {
        return $this->hasMany(QardHasanRepayment::class)->orderByDesc('paid_at');
    }

    public function guarantors()
    {
        return $this->belongsToMany(User::class, 'qard_hasan_guarantors', 'qard_hasan_id', 'guarantor_id')
            ->withTimestamps()
            ->withPivot(['status', 'token', 'responded_at', 'nudge_count', 'last_nudged_at', 'escalated_at']);
    }

    public function transactionApprovals(): MorphMany
    {
        return $this->morphMany(TransactionApproval::class, 'approvable');
    }

    public function ledgerJournal()
    {
        return $this->belongsTo(LedgerJournal::class);
    }
}
