<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecurringJournal extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'amount',
        'debit_account_id',
        'credit_account_id',
        'day_of_month',
        'reference_prefix',
        'active',
        'last_posted_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'day_of_month' => 'integer',
        'active' => 'boolean',
        'last_posted_at' => 'datetime',
    ];
}
