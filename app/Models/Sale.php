<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'document_number',
        'party_id',
        'warehouse_id',
        'invoice_date',
        'due_date',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'paid_amount',
        'remaining_amount',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'invoice_date' => 'datetime',
        'due_date' => 'datetime',
        'status' => DocumentStatus::class,
    ];

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party()
    {
        return $this->belongsTo(Party::class);
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return HasMany<SaleItem, $this>
     */
    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
