<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderAccount extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'account_number',
        'account_name',
        'bank_name',
        'bank_code',
        'provider_customer_code',
        'provider_authorization_code',
        'metadata',
        'is_active',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
