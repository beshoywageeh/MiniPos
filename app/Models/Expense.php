<?php

namespace App\Models;

use App\Enums\ExpenseCategory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'document_number',
        'expense_category',
        'cashbox_id',
        'amount',
        'expense_date',
        'description',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'expense_category' => ExpenseCategory::class,
        'expense_date' => 'date',
    ];

    public function cashbox()
    {
        return $this->belongsTo(Cashbox::class);
    }
}
