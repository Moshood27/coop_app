<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AttendanceRecord extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'user_id',
        'meeting_id',
        'fine_amount',
        'marked_by_id',
        'status',
        'attended_at',
        'lat',
        'lng',
        'device_uuid',
        'fine_paid_at',
        'lateness_fine_paid',
        'lateness_fine_amount',
        'excuse_reason',
        'excuse_type',
        'excuse_proof_path',
        'excused_at',
        'verified_biometrically',
        'verified_via_beacon',
        'is_offline_sync',
    ];

    protected $casts = [
        'attended_at' => 'datetime',
        'fine_paid_at' => 'datetime',
        'fine_amount' => 'decimal:2',
        'lat' => 'decimal:8',
        'lng' => 'decimal:8',
        'lateness_fine_paid' => 'boolean',
        'lateness_fine_amount' => 'decimal:2',
        'excuse_type' => 'string',
        'excuse_proof_path' => 'string',
        'excused_at' => 'datetime',
        'verified_biometrically' => 'boolean',
        'verified_via_beacon' => 'boolean',
        'is_offline_sync' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'fine_paid_at', 'lateness_fine_paid', 'lateness_fine_amount', 'fine_amount'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by_id');
    }
}
