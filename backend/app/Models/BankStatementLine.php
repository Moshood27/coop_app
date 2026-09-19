<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankStatementLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_statement_id',
        'txn_date',
        'amount',
        'description',
        'reference',
        'hash',
        'status',
        'matched_journal_id',
        'matched_entry_id',
    ];

    protected $casts = [
        'txn_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function statement(): BelongsTo
    {
        return $this->belongsTo(BankStatement::class, 'bank_statement_id');
    }

    public function matchedJournal(): BelongsTo
    {
        return $this->belongsTo(LedgerJournal::class, 'matched_journal_id');
    }

    public function matchedEntry(): BelongsTo
    {
        return $this->belongsTo(LedgerEntry::class, 'matched_entry_id');
    }
}
