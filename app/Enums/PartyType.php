<?php

namespace App\Enums;

enum PartyType
{
    case CUSTOMER;
    case SUPPLIER;
    case BOTH;
    public function label(): string
    {
        return match ($this) {
            self::CUSTOMER => __('parties.customer'),
            self::SUPPLIER => __('parties.supplier'),
            self::BOTH => __('parties.both'),
        };
    }
}
