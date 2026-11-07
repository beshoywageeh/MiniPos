<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesReturnItem extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'sales_return_id',
        'product_id',
        'quantity',
        'unit_price',
        'cost_price',
        'line_total',
    ];

    public function salesReturn()
    {
        return $this->belongsTo(SalesReturn::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
