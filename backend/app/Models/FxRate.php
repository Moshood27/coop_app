<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FxRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'currency_code', 'rate_date', 'rate_to_base',
    ];

    protected $casts = [
        'rate_date' => 'date',
    ];
}
