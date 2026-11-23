<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

class ProductMatcher
{
    /**
     * Constrain a products query to those matching the term by barcode, SKU,
     * code or name, ranking exact barcode matches first (scanner friendly).
     */
    public static function apply(Builder $query, string $term): Builder
    {
        $term = trim($term);

        if ($term === '') {
            return $query->orderBy('name');
        }

        return $query
            ->where(function (Builder $query) use ($term): void {
                $query->where('barcode', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%")
                    ->orWhere('name', 'like', "%{$term}%");
            })
            ->orderByRaw('barcode = ? desc', [$term])
            ->orderBy('name');
    }

    /**
     * Look up active products matching the term.
     *
     * @return list<Product>
     */
    public static function search(string $term, int $limit = 10): array
    {
        /** @var list<Product> $products */
        $products = self::apply(Product::query()->where('is_active', true), $term)
            ->with('unit')
            ->limit($limit)
            ->get()
            ->all();

        return $products;
    }
}
