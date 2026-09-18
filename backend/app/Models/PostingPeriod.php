<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostingPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'fiscal_year_id',
        'name', // e.g., 2026-01
        'start_date',
        'end_date',
        'is_open',
        'sequence', // 1..12 (or 13+ for adjustments)
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_open' => 'boolean',
    ];

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }
}
