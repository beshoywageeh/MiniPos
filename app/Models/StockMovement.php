<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
