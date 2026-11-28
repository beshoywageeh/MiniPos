<?php

namespace App\Support;

use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Sale;
use App\Models\SalesReturn;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;

class StockMovementSource
{
    /**
     * Describe the source document of a movement for the ledger.
     *
     * @return array{label: string, url: ?string}
     */
    public static function describe(StockMovement $movement): array
    {
        $referenceId = $movement->reference_id;

        return match ($movement->reference_type) {
            Sale::class => self::describeDocument(
                $referenceId === null ? null : Sale::query()->find($referenceId),
                'sale',
                'sales.invoice_preview',
            ),
            Purchase::class => self::describeDocument(
                $referenceId === null ? null : Purchase::query()->find($referenceId),
                'purchase',
                'purchases.invoice_preview',
            ),
            StockAdjustment::class => self::describeDocument(
                $referenceId === null ? null : StockAdjustment::query()->find($referenceId),
                'stock_adjustment',
                'stock-adjustments.preview',
            ),
            default => [
                'label' => self::typeLabel($movement),
                'url' => null,
            ],
        };
    }

    /**
     * Return the generic label for a movement's source type.
     */
    public static function typeLabel(StockMovement $movement): string
    {
        return match ($movement->reference_type) {
            Sale::class => (string) __('stock_movements.source_sale'),
            Purchase::class => (string) __('stock_movements.source_purchase'),
            StockAdjustment::class => (string) __('stock_movements.source_stock_adjustment'),
            SalesReturn::class => (string) __('stock_movements.source_sales_return'),
            PurchaseReturn::class => (string) __('stock_movements.source_purchase_return'),
            'adjustment' => (string) __('stock_movements.source_legacy_adjustment'),
            default => '—',
        };
    }

    /**
     * Build the source cell from a resolved document, falling back to its type label.
     *
     * @return array{label: string, url: ?string}
     */
    private static function describeDocument(?Model $document, string $labelKey, string $routeName): array
    {
        return [
            'label' => $document === null
                ? (string) __('stock_movements.source_'.$labelKey)
                : (string) $document->getAttribute('document_number'),
            'url' => $document === null ? null : route($routeName, $document),
        ];
    }
}
