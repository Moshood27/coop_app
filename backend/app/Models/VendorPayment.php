<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_bill_id',
        'date',
        'amount',
        'reference',
        'ledger_journal_id',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(VendorBill::class, 'vendor_bill_id');
    }

    public function ledgerJournal(): BelongsTo
    {
        return $this->belongsTo(LedgerJournal::class);
    }
}
