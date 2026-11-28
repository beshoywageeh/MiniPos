<?php

namespace App\Enums;

enum StockAdjustmentReason: string
{
    case CountCorrection = 'count_correction';
    case OpeningStock = 'opening_stock';
    case Damage = 'damage';
    case Expiry = 'expiry';
    case Other = 'other';

    public function label(): string
    {
        return __('StockAdjustmentReason.'.$this->name);
    }
}
