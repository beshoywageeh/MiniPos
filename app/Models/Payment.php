<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'document_number',
        'party_id',
        'cashbox_id',
        'sale_id',
        'purchase_id',
        'expense_id',
        'type',
        'method',
        'amount',
        'payment_date',
        'reference_number',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'payment_date' => 'datetime',
        'type' => PaymentType::class,
        'method' => PaymentMethod::class,
    ];

    public function party()
    {
        return $this->belongsTo(Party::class);
    }

    public function cashbox()
    {
        return $this->belongsTo(Cashbox::class);
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    public function expense()
    {
        return $this->belongsTo(Expense::class);
    }
}
