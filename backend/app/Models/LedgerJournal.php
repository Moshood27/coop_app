<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class LedgerJournal extends Model
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
        'date',
        'number',
        'currency_code',
        'fx_rate',
        'reference',
        'external_key',
        'description',
        'status',
        'approved_by',
        'approved_at',
        'posted_at',
        'period_id',
        'reversing_journal_id',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'approved_at' => 'datetime',
        'posted_at' => 'datetime',
    ];

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class, 'period_id');
    }

    public function reversingJournal(): BelongsTo
    {
        return $this->belongsTo(LedgerJournal::class, 'reversing_journal_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(LedgerAttachment::class, 'ledger_journal_id');
    }

    /**
     * Check if the journal is balanced.
     */
    public function isBalanced(): bool
    {
        $debits = $this->entries()->sum('debit');
        $credits = $this->entries()->sum('credit');

        return round((float)$debits, 2) === round((float)$credits, 2);
    }
}
