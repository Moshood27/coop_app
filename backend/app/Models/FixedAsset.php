<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FixedAsset extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
        'acquisition_date',
        'cost',
        'useful_life_months',
        'residual_value',
        'depreciation_method', // straight_line, declining_balance
        'ledger_journal_id', // last depreciation journal or acquisition
        'is_disposed',
        'disposed_at',
        'disposal_amount',
    ];

    protected $casts = [
        'acquisition_date' => 'date',
        'disposed_at' => 'date',
        'cost' => 'decimal:2',
        'residual_value' => 'decimal:2',
        'disposal_amount' => 'decimal:2',
        'is_disposed' => 'boolean',
    ];
}
