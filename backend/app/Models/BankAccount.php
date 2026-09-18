<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'bank_name',
        'account_number',
        'currency',
        'gl_account_id', // control account link (optional)
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
