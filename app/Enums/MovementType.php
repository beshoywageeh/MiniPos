<?php

namespace App\Enums;

enum MovementType
{
    case INCOME;
    case EXPENSE;
    public function label(): string
    {
        return match ($this) {
            self::INCOME => __('Movment.Income'),
            self::EXPENSE => __('Movment.Expense'),
        };
    }
}
