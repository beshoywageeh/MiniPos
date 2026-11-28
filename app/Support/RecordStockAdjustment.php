<?php

namespace App\Support;

use App\Enums\MovementType;
use App\Enums\ProductType;
use App\Enums\StockAdjustmentReason;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RecordStockAdjustment
{
    public function __construct(public StockMovementRecorder $recorder) {}

    /**
     * @param  array{product_id: int, warehouse_id: int, movement_type: MovementType, quantity: string|float|int, unit_cost?: string|float|int|null, reason: StockAdjustmentReason, note?: string|null, movement_date: string, is_override?: bool, created_by?: ?int}  $attributes
     */
    public static function execute(array $attributes): StockAdjustment
    {
        return app(static::class)->record($attributes);
    }

    /**
     * @param  array{product_id: int, warehouse_id: int, movement_type: MovementType, quantity: string|float|int, unit_cost?: string|float|int|null, reason: StockAdjustmentReason, note?: string|null, movement_date: string, is_override?: bool, created_by?: ?int}  $attributes
     */
    public function record(array $attributes): StockAdjustment
    {
        $payload = $attributes;

        $payload['movement_type'] = $payload['movement_type']->value;
        $payload['reason'] = $payload['reason']->value;

        $validated = Validator::make($payload, [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'movement_type' => ['required', Rule::in(array_column(MovementType::cases(), 'value'))],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['required', Rule::in(array_column(StockAdjustmentReason::cases(), 'value'))],
            'note' => ['nullable', 'string'],
            'movement_date' => ['required', 'date'],
            'is_override' => ['boolean'],
            'created_by' => ['nullable', 'integer', 'exists:users,id'],
        ])->validate();

        /** @var Product $product */
        $product = Product::query()->findOrFail($validated['product_id']);
        /** @var Warehouse $warehouse */
        $warehouse = Warehouse::query()->findOrFail($validated['warehouse_id']);
        $movementType = MovementType::from($validated['movement_type']);
        $reason = StockAdjustmentReason::from($validated['reason']);
        $isOverride = (bool) ($validated['is_override'] ?? false);
        $note = $validated['note'] ?? null;
        $quantity = (float) $validated['quantity'];
        $createdBy = $validated['created_by'] ?? Auth::id();

        if ($product->type !== ProductType::Product) {
            throw ValidationException::withMessages([
                'product_id' => __('stock_adjustments.service_product'),
            ]);
        }

        if ($isOverride && trim((string) $note) === '') {
            throw ValidationException::withMessages([
                'note' => __('stock_adjustments.override_note_required'),
            ]);
        }

        if ($movementType === MovementType::OUT && ! $isOverride && $quantity > $this->recorder->availableStock($product, $warehouse)) {
            throw ValidationException::withMessages([
                'quantity' => __('stock_adjustments.insufficient_stock'),
            ]);
        }

        return DB::transaction(function () use (
            $product,
            $warehouse,
            $movementType,
            $reason,
            $quantity,
            $note,
            $isOverride,
            $createdBy,
            $validated,
        ): StockAdjustment {
            $adjustment = StockAdjustment::query()->create([
                'document_number' => DocumentNumber::next('stock_adjustment'),
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'movement_type' => $movementType,
                'quantity' => $quantity,
                'unit_cost' => $validated['unit_cost'] ?? 0,
                'reason' => $reason,
                'note' => $note,
                'is_override' => $isOverride,
                'movement_date' => $validated['movement_date'],
                'created_by' => $createdBy,
            ]);

            $this->recorder->record([
                'product' => $product,
                'warehouse' => $warehouse,
                'movement_type' => $movementType,
                'quantity' => $quantity,
                'unit_cost' => $validated['unit_cost'] ?? 0,
                'reference_type' => StockAdjustment::class,
                'reference_id' => $adjustment->id,
                'note' => $note,
                'movement_date' => $validated['movement_date'],
                'created_by' => $createdBy,
                'is_override' => $isOverride,
            ]);

            return $adjustment;
        });
    }
}
