<?php

namespace App\Models;

use App\Models\Traits\QardHasanAttributes;
use App\Models\Traits\QardHasanCalculations;
use App\Models\Traits\QardHasanMultiSig;
use App\Models\Traits\QardHasanRelations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class QardHasan extends Model
{
    use HasFactory, LogsActivity;
    use QardHasanRelations, QardHasanAttributes, QardHasanCalculations, QardHasanMultiSig;

    protected $table = 'qard_hasans';

    protected $fillable = [
        'user_id', 'qard_id_string', 'description', 'principal_amount', 'total_installments',
        'per_installment', 'interval', 'admin_fee_flat', 'admin_fee_pct', 'paid_amount',
        'status', 'approved_by', 'approved_at', 'received_at', 'disbursed_at',
        'repayment_start_date', 'defaulted_at', 'ledger_journal_id', 'agreement_template',
        'signed_agreement', 'agreement_uploaded_at', 'agreement_verified_at', 'agreement_rejection_reason',
        'meeting_attendance_count'
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'received_at' => 'datetime',
        'disbursed_at' => 'datetime',
        'repayment_start_date' => 'date',
        'defaulted_at' => 'datetime',
        'agreement_uploaded_at' => 'datetime',
        'agreement_verified_at' => 'datetime',
        'principal_amount' => 'float',
        'paid_amount' => 'float',
        'per_installment' => 'float',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    protected static function booted(): void
    {
        static::updating(function (QardHasan $loan) {
            if ($loan->isDirty('defaulted_at') && !$loan->getOriginal('defaulted_at') && $loan->defaulted_at) {
                $loan->startPenaltyRecord();
            }
            if ($loan->isDirty('defaulted_at') && $loan->getOriginal('defaulted_at') && !$loan->defaulted_at) {
                $loan->completePenaltyRecord();
            }
        });

        static::saving(function (QardHasan $loan) {
            if ($loan->paid_amount >= $loan->principal_amount && $loan->principal_amount > 0) {
                if (!in_array($loan->status, ['cancelled', 'rejected'])) $loan->status = 'completed';
                if ($loan->defaulted_at) $loan->defaulted_at = null;
            }
            if ($loan->status !== 'completed' && $loan->defaulted_at && $loan->defaulted_at->year > 1970 && $loan->defaulted_at->lte(now())) {
                if (in_array($loan->status, ['active', 'pending'])) $loan->status = 'defaulted';
            } elseif ($loan->status === 'defaulted') {
                if (!$loan->defaulted_at || $loan->defaulted_at->gt(now())) $loan->status = 'active';
            }
        });

        static::updated(fn (QardHasan $loan) => $loan->wasChanged(['defaulted_at', 'status', 'paid_amount']) ? $loan->syncUserDefaulterStatus() : null);
        static::created(fn (QardHasan $loan) => $loan->defaulted_at ? $loan->syncUserDefaulterStatus() : null);

        static::deleting(function (QardHasan $qardHasan) {
            $qardHasan->repayments()->each(function ($repayment) {
                if ($repayment->ledger_journal_id) \App\Models\LedgerJournal::find($repayment->ledger_journal_id)?->delete();
                $repayment->delete();
            });
            if ($qardHasan->ledger_journal_id) \App\Models\LedgerJournal::find($qardHasan->ledger_journal_id)?->delete();
            $qardHasan->transactionApprovals()->each(fn($a) => $a->delete());
            $qardHasan->guarantors()->detach();
            \App\Models\LoanPenalty::where('qard_hasan_id', $qardHasan->id)->get()->each(fn($p) => $p->delete());
        });

        static::deleted(function (QardHasan $qardHasan) {
            $qardHasan->syncUserDefaulterStatus();
            if ($qardHasan->user) {
                try {
                    app(\App\Services\AttaqwaScoreService::class)->calculateAndUpdateScore($qardHasan->user);
                } catch (\Throwable $e) {}
            }
        });
    }

    public function allGuarantorsAccepted(): bool
    {
        $guarantors = $this->guarantors;
        if ($guarantors->isEmpty()) return false;
        return $guarantors->every(fn($g) => $g->pivot->status === 'accepted');
    }

    public function pendingGuarantorCount(): int
    {
        return $this->guarantors()->wherePivot('status', '!=', 'accepted')->count();
    }

    public function syncUserDefaulterStatus(): void
    {
        if ($this->user) $this->user->syncLoanDefaulterStatus();
    }
}