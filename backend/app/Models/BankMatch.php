<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankMatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'reconciliation_id',
        'bank_statement_line_id',
        'ledger_journal_id',
        'ledger_entry_id',
        'match_amount',
        'method', // auto | manual
    ];

    protected $casts = [
        'match_amount' => 'decimal:2',
    ];

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(BankReconciliation::class, 'reconciliation_id');
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(BankStatementLine::class, 'bank_statement_line_id');
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(LedgerJournal::class, 'ledger_journal_id');
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(LedgerEntry::class, 'ledger_entry_id');
    }
}
