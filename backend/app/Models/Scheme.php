<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Scheme extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'name',
        'min_amount',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function contributions()
    {
        return $this->hasMany(Contribution::class);
    }

    public static function getSortedOptions($activeOnly = false, $withTrashed = false, $withCombined = false)
    {
        $query = static::query();
        if ($activeOnly) $query->where('active', true);
        if ($withTrashed) $query->withTrashed();

        $schemes = $query->orderBy('name')->get();
        $options = $schemes->pluck('name', 'id')->toArray();

        $shares = $schemes->first(fn($s) => $s->name === 'Shares' || \Illuminate\Support\Str::contains(strtolower($s->name), 'share'));
        $savings = $schemes->first(fn($s) => $s->name === 'Savings' || \Illuminate\Support\Str::contains(strtolower($s->name), 'saving'));

        if ($shares || $savings) {
            $top = [];
            if ($withCombined && $shares && $savings) {
                $top['combined'] = 'Shares & Savings (50/50 Split)';
                unset($options[$shares->id]);
                unset($options[$savings->id]);
            } else {
                if ($shares) {
                    $top[$shares->id] = $shares->name;
                    unset($options[$shares->id]);
                }
                if ($savings) {
                    $top[$savings->id] = $savings->name;
                    unset($options[$savings->id]);
                }
            }
            $options = $top + $options;
        }
        return $options;
    }
}
