<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_order_id',
        'store_order_item_id',
        'direction', // input|output
        'rate_percent',
        'base_amount',
        'tax_amount',
        'meta',
    ];

    protected $casts = [
        'rate_percent' => 'decimal:2',
        'base_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'meta' => 'array',
    ];
}
