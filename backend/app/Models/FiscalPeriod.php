<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon; // alias not needed but kept for IDE

class FiscalPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'starts_on', 'ends_on', 'is_closed', 'closed_at', 'closed_by',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'is_closed' => 'boolean',
        'closed_at' => 'datetime',
    ];

    public function scopeContainingDate($query, $date)
    {
        return $query->where('starts_on', '<=', $date)->where('ends_on', '>=', $date);
    }
}
