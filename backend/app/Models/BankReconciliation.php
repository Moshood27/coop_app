<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankReconciliation extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_account_id',
        'period_from',
        'period_to',
        'ending_balance',
        'status',
        'reconciled_by',
    ];

    protected $casts = [
        'period_from' => 'date',
        'period_to' => 'date',
        'ending_balance' => 'decimal:2',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function reconciler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(BankMatch::class, 'reconciliation_id');
    }
}
