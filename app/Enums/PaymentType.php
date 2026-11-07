<?php

namespace App\Enums;

enum PaymentType
{
    case recipt;
    case payment;
    public function label(): string
    {
        return match ($this) {
            self::recipt => __('PaymentType.Recipt'),
            self::payment => __('PaymentType.Payment'),
        };
    }
}
