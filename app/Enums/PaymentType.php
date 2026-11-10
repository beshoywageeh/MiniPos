<?php

namespace App\Enums;

enum PaymentType: string
{
    case recipt = 'receipt';
    case payment = 'payment';

    public function label(): string
    {
        return match ($this) {
            self::recipt => __('PaymentType.Recipt'),
            self::payment => __('PaymentType.Payment'),
        };
    }
}
