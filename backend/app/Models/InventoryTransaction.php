<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'branch_id', 'type', 'qty', 'unit_cost', 'total_cost',
        'reference_type', 'reference_id', 'ledger_journal_id', 'performed_at', 'created_by',
    ];

    protected $casts = [
        'performed_at' => 'datetime',
        'qty' => 'float',
        'unit_cost' => 'float',
        'total_cost' => 'float',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
