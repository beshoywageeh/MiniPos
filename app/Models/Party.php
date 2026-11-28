<?php

namespace App\Models;

use App\Enums\PartyType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Party extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'type',
        'phone',
        'address',
        'note',
        'is_active',
    ];

    protected $casts = [
        'type' => PartyType::class,
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

    public function brands()
    {
        return $this->belongsToMany(Brand::class);
    }
}
