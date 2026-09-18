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
        'date',
        'description',
        'amount',
        'balance',
        'external_reference',
        'is_matched',
        'matched_ledger_entry_id',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'is_matched' => 'boolean',
    ];

    public function statement(): BelongsTo
    {
        return $this->belongsTo(BankStatement::class, 'bank_statement_id');
    }

    public function matchedEntry(): BelongsTo
    {
        return $this->belongsTo(LedgerEntry::class, 'matched_ledger_entry_id');
    }
}
