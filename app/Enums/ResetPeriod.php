<?php

namespace App\Enums;

enum ResetPeriod
{
    case DAILY;
    case WEEKLY;
    case MONTHLY;
    case YEARLY;

    public function label(): string
    {
        return match ($this) {
            self::DAILY => __('ResetPeriod.Daily'),
            self::WEEKLY => __('ResetPeriod.Weekly'),
            self::MONTHLY => __('ResetPeriod.Monthly'),
            self::YEARLY => __('ResetPeriod.Yearly'),
        };
    }
}
