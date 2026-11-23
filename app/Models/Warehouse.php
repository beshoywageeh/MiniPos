<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'code',
        'name',
        'address',
        'note',
        'is_active',
    ];

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }

    public function purchaseReturns()
    {
        return $this->hasMany(PurchaseReturn::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function salesReturns()
    {
        return $this->hasMany(SalesReturn::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }
}
