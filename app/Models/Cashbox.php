<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cashbox extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'code',
        'name',
        'description',
        'opening_balance',
        'is_active',
    ];

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }
}
