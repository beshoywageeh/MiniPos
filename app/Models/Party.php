<?php

namespace App\Models;

use App\Enums\PartyType;
use Illuminate\Database\Eloquent\Model;

class Party extends Model
{
    protected $fillable = [
        'code',
        'name',
        'type',
        'phone',
        'email',
        'address',
        'tax_number',
        'note',
        'is_active',
    ];
    protected $casts = [
        'type' => PartyType::class
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

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
