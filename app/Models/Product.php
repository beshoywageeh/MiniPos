<?php

namespace App\Models;

use App\Enums\ProductType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory;
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'code',
        'sku',
        'barcode',
        'name',
        'type',
        'description',
        'category_id',
        'brand_id',
        'unit_id',
        'purchase_price',
        'selling_price',
        'min_stock',
        'is_active',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'type' => ProductType::class,
        'is_active' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }
}
