<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecurringJournal extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'schedule', 'next_run_at', 'last_run_at', 'is_active', 'template', 'description', 'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'next_run_at' => 'datetime',
        'last_run_at' => 'datetime',
        'template' => 'array',
    ];
}
