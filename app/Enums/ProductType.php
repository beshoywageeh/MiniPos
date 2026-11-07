<?php

namespace App\Enums;

enum ProductType
{
    case PRODUCT;
    case SERVICE;
    public function label(): string
    {
        return match ($this) {
            self::PRODUCT => __('ProductType.Product'),
            self::SERVICE => __('ProductType.Service'),
        };
    }
}
