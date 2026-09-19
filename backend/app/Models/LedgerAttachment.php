<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'ledger_journal_id', 'path', 'original_name', 'mime_type', 'size_bytes', 'uploaded_by',
    ];

    public function journal(): BelongsTo
    {
        return $this->belongsTo(LedgerJournal::class, 'ledger_journal_id');
    }
}
