<?php

namespace App\Http\Controllers;

use App\Enums\ProductType;
use App\Models\Product;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductLookupController extends Controller
{
    /**
     * Look up active products by code, SKU, barcode or name for the POS screen.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $searchTerm = trim((string) $request->query('q', ''));

        $products = Product::query()
            ->where('is_active', true)
            ->when($searchTerm !== '', function (Builder $query) use ($searchTerm): void {
                $query->where(function (Builder $query) use ($searchTerm): void {
                    $query->where('barcode', 'like', "%{$searchTerm}%")
                        ->orWhere('sku', 'like', "%{$searchTerm}%")
                        ->orWhere('code', 'like', "%{$searchTerm}%")
                        ->orWhere('name', 'like', "%{$searchTerm}%");
                });
            })
            ->with('unit')
            ->orderByRaw('barcode = ? desc', [$searchTerm])
            ->orderBy('name')
            ->limit(10)
            ->get();

        return response()->json([
            'data' => $products->map(fn (Product $product): array => [
                'id' => $product->id,
                'code' => $product->code,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'name' => $product->name,
                'type' => $product->type,
                'selling_price' => (float) $product->selling_price,
                'unit_name' => $product->unit?->name,
                'is_service' => $product->type === ProductType::Service,
            ]),
        ]);
    }
}
