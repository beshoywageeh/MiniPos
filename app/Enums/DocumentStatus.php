<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case PAID = 'paid';
    case PARTIAL = 'partial';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => __('DocumentStatus.Draft'),
            self::PENDING => __('DocumentStatus.Pending'),
            self::APPROVED => __('DocumentStatus.Approved'),
            self::PAID => __('DocumentStatus.Paid'),
            self::PARTIAL => __('DocumentStatus.Partial'),
        };
    }
}
