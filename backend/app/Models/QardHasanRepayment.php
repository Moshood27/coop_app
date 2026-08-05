<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class QardHasanRepayment extends Model
{
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'qard_hasan_id',
        'amount',
        'payment_method',
        'reference',
        'status',
        'paid_at',
        'notes',
        'ledger_journal_id',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (!$model->reference) {
                $model->reference = 'LRP-' . strtoupper(\Illuminate\Support\Str::random(12));
            }
        });

        static::updated(function (self $model) {
            if ($model->status === 'success' && $model->wasChanged('status')) {
                try {
                    if ($model->qardHasan && $model->qardHasan->user) {
                        app(\App\Services\AttaqwaScoreService::class)->calculateAndUpdateScore($model->qardHasan->user);
                    }
                } catch (\Throwable $e) {}
            }
        });

        static::created(function (self $model) {
            if ($model->status === 'success') {
                try {
                    if ($model->qardHasan && $model->qardHasan->user) {
                        app(\App\Services\AttaqwaScoreService::class)->calculateAndUpdateScore($model->qardHasan->user);
                    }
                } catch (\Throwable $e) {}
            }
        });
    }

    public function qardHasan()
    {
        return $this->belongsTo(QardHasan::class);
    }

    public function ledgerJournal()
    {
        return $this->belongsTo(LedgerJournal::class);
    }
}
