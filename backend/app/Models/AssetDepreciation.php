<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetDepreciation extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id',
        'period_start',
        'period_end',
        'amount',
        'ledger_journal_id',
        'posted_at',
        'status',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'amount' => 'decimal:2',
        'posted_at' => 'datetime',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(LedgerJournal::class, 'ledger_journal_id');
    }
}
