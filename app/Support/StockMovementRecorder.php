<?php

namespace App\Support;

use App\Enums\MovementType;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class StockMovementRecorder
{
    public function availableStock(Product $product, Warehouse $warehouse): float
    {
        $result = StockMovement::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->selectRaw('COALESCE(SUM(CASE WHEN movement_type = ? THEN quantity ELSE -quantity END), 0) as available', [MovementType::IN->value])
            ->first();

        return (float) ($result?->available ?? 0);
    }

    /**
     * @param  array{product: Product, warehouse: Warehouse, movement_type: MovementType, quantity: string|float|int, unit_cost?: string|float|int|null, reference_type?: ?string, reference_id?: ?int, note?: ?string, movement_date: string, created_by?: ?int, is_override?: bool}  $attributes
     */
    public function record(array $attributes): StockMovement
    {
        /** @var Product $product */
        $product = $attributes['product'];
        /** @var Warehouse $warehouse */
        $warehouse = $attributes['warehouse'];
        /** @var MovementType $movementType */
        $movementType = $attributes['movement_type'];

        return DB::transaction(function () use ($attributes, $product, $warehouse, $movementType): StockMovement {
            StockMovement::query()
                ->where('product_id', $product->id)
                ->where('warehouse_id', $warehouse->id)
                ->lockForUpdate()
                ->get(['id']);

            $quantity = (float) $attributes['quantity'];
            $signedQuantity = $movementType === MovementType::IN ? $quantity : -$quantity;
            $balanceAfter = $this->availableStock($product, $warehouse) + $signedQuantity;

            return StockMovement::query()->create([
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'movement_type' => $movementType->value,
                'quantity' => $quantity,
                'unit_cost' => $attributes['unit_cost'] ?? 0,
                'reference_type' => $attributes['reference_type'] ?? null,
                'reference_id' => $attributes['reference_id'] ?? null,
                'note' => $attributes['note'] ?? null,
                'balance_after' => $balanceAfter,
                'is_override' => $attributes['is_override'] ?? false,
                'movement_date' => $attributes['movement_date'],
                'created_by' => $attributes['created_by'] ?? null,
            ]);
        });
    }
}
