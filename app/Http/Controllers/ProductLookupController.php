<?php

namespace App\Http\Controllers;

use App\Enums\ProductType;
use App\Models\Product;
use App\Support\ProductMatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductLookupController extends Controller
{
    /**
     * Look up active products by code, SKU, barcode or name for the POS screen.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $products = ProductMatcher::search(trim((string) $request->query('q', '')));

        return response()->json([
            'data' => collect($products)->map(fn (Product $product): array => [
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
