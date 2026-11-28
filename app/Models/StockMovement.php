<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use HasFactory;

    /** @var array<string, string> */
    protected $casts = [
        'is_override' => 'boolean',
    ];

    /** @var list<string> */
    protected $fillable = [
        'product_id',
        'warehouse_id',
        'movement_type',
        'quantity',
        'unit_cost',
        'balance_after',
        'is_override',
        'reference_type',
        'reference_id',
        'note',
        'movement_date',
        'created_by',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
}
