<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Support\Accounting as AccountingSupport;

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
        'reference',
        'description',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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

    /**
     * Determine if the journal has been posted (immutable).
     * This is schema-aware and returns false if no status columns exist.
     */
    public function isPosted(): bool
    {
        // Support either posted_at datetime or status column
        if (AccountingSupport::columnExists('ledger_journals', 'posted_at')) {
            return !empty($this->getAttribute('posted_at'));
        }
        if (AccountingSupport::columnExists('ledger_journals', 'status')) {
            $status = strtolower((string) $this->getAttribute('status'));
            return $status === 'posted' || $status === 'locked';
        }
        return false;
    }

    protected static function booted(): void
    {
        static::updating(function (self $model) {
            if ($model->exists && $model->getOriginal() && method_exists($model, 'isPosted') && $model->isPosted()) {
                // Prevent editing posted journals at the application layer
                throw new \RuntimeException('Posted journal entries cannot be modified. Create an adjustment journal instead.');
            }
        });

        static::deleting(function (self $model) {
            if (method_exists($model, 'isPosted') && $model->isPosted()) {
                throw new \RuntimeException('Posted journal entries cannot be deleted. Create an adjustment journal instead.');
            }
        });
    }
}
