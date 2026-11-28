<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesReturn extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'document_number',
        'sale_id',
        'party_id',
        'warehouse_id',
        'return_date',
        'total_amount',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'return_date' => 'datetime',
        'status' => DocumentStatus::class,
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function party()
    {
        return $this->belongsTo(Party::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items()
    {
        return $this->hasMany(SalesReturnItem::class);
    }
}
