<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_invoice_id',
        'date',
        'amount',
        'reference',
        'ledger_journal_id',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(CustomerInvoice::class, 'customer_invoice_id');
    }

    public function ledgerJournal(): BelongsTo
    {
        return $this->belongsTo(LedgerJournal::class);
    }
}
