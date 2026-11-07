<?php

namespace App\Enums;

enum DocumentStatus
{
    case DRAFT;
    case PENDING;
    case APPROVED;
    public  function label(): string
    {
        return match ($this) {
            self::DRAFT => __('DocumentStatus.Draft'),
            self::PENDING => __('DocumentStatus.Pending'),
            self::APPROVED => __('DocumentStatus.Approved'),
        };
    }
}
