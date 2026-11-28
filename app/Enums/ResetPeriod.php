<?php

namespace App\Enums;

enum ResetPeriod: string
{
    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';
    case YEARLY = 'yearly';
    case NEVER = 'never';

    public function label(): string
    {
        return match ($this) {
            self::DAILY => __('ResetPeriod.Daily'),
            self::WEEKLY => __('ResetPeriod.Weekly'),
            self::MONTHLY => __('ResetPeriod.Monthly'),
            self::YEARLY => __('ResetPeriod.Yearly'),
            self::NEVER => __('ResetPeriod.Never'),
        };
    }
}
