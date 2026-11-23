<?php

namespace App\Enums;

enum PartyType: string
{
    case CUSTOMER = 'customer';
    case SUPPLIER = 'supplier';
    case BOTH = 'both';

    public function label(): string
    {
        return match ($this) {
            self::CUSTOMER => __('parties.customer'),
            self::SUPPLIER => __('parties.supplier'),
            self::BOTH => __('parties.both'),
        };
    }
}
