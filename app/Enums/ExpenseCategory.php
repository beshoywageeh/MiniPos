<?php

namespace App\Enums\Enums;

enum ExpenseCategory
{
    case OFFICE;
    case TRAVEL;
    case ENTERTAINMENT;
    case UTILITIES;
    case SALARIES;
    case RENT;
    case MAINTENANCE;
    case INSURANCE;
    case TAXES;
    case ADVERTISING;

    public function label(): string
    {
        return match ($this) {
            self::OFFICE => __('expenses.office'),
            self::TRAVEL => __('expenses.travel'),
            self::ENTERTAINMENT => __('expenses.entertainment'),
            self::UTILITIES => __('expenses.utilities'),
            self::SALARIES => __('expenses.salaries'),
            self::RENT => __('expenses.rent'),
            self::MAINTENANCE => __('expenses.maintenance'),
            self::INSURANCE => __('expenses.insurance'),
            self::TAXES => __('expenses.taxes'),
            self::ADVERTISING => __('expenses.advertising'),
        };
    }
}
