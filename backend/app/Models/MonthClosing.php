<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthClosing extends Model
{
    use HasFactory;

    protected $fillable = [
        'year',
        'month',
        'closed_at',
        'closed_by',
    ];

    protected $casts = [
        'closed_at' => 'datetime',
        'year' => 'integer',
        'month' => 'integer',
    ];

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public static function isClosed(int $year, int $month): bool
    {
        return self::where('year', $year)->where('month', $month)->exists();
    }

    public static function isDateClosed($date): bool
    {
        if (!$date) return false;
        $carbonDate = \Carbon\Carbon::parse($date);
        return self::isClosed($carbonDate->year, $carbonDate->month);
    }
}
