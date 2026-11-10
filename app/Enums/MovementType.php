<?php

namespace App\Enums;

enum MovementType: string
{
    case IN = 'in';
    case OUT = 'out';

    public function label(): string
    {
        return match ($this) {
            self::IN => __('Movment.Income'),
            self::OUT => __('Movment.Expense'),
        };
    }
}
