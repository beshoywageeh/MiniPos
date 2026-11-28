<?php

namespace App\Models;

use App\Enums\MovementType;
use App\Enums\StockAdjustmentReason;
use Database\Factories\StockAdjustmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class StockAdjustment extends Model
{
    /** @use HasFactory<StockAdjustmentFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'document_number',
        'product_id',
        'warehouse_id',
        'movement_type',
        'quantity',
        'unit_cost',
        'reason',
        'note',
        'is_override',
        'movement_date',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'movement_type' => MovementType::class,
        'reason' => StockAdjustmentReason::class,
        'is_override' => 'boolean',
        'movement_date' => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function movement(): MorphOne
    {
        return $this->morphOne(StockMovement::class, 'reference');
    }
}
