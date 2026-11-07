<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseReturnItem extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'purchase_return_id',
        'product_id',
        'quantity',
        'unit_cost',
        'line_total',
    ];

    public function purchaseReturn()
    {
        return $this->belongsTo(PurchaseReturn::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
