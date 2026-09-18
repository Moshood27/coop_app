<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccrualSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'amount_total',
        'debit_account_id',
        'credit_account_id',
        'auto_reverse',
        'posted_until',
        'active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'posted_until' => 'date',
        'amount_total' => 'decimal:2',
        'auto_reverse' => 'boolean',
        'active' => 'boolean',
    ];
}
