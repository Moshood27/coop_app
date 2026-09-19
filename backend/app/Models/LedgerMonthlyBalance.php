<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LedgerMonthlyBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'ledger_account_id', 'year', 'month', 'branch_id', 'opening_balance', 'debits', 'credits', 'closing_balance',
    ];
}
