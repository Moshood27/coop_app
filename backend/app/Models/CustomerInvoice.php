<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'number',
        'date',
        'due_date',
        'currency',
        'amount',
        'status', // draft, posted, paid, void
        'ledger_journal_id',
    ];

    protected $casts = [
        'date' => 'date',
        'due_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function ledgerJournal(): BelongsTo
    {
        return $this->belongsTo(LedgerJournal::class);
    }
}
