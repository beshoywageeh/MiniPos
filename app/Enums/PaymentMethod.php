<?php

namespace App\Enums;

enum PaymentMethod
{
    case CASH;
    case BANK_TRANSFER;
    case CREDIT_CARD;
    case WALLET;
    public function label(): string
    {
        return match ($this) {
            self::CASH => __('PaymentMethod.Cash'),
            self::BANK_TRANSFER => __('PaymentMethod.BankTransfer'),
            self::CREDIT_CARD => __('PaymentMethod.CreditCard'),
            self::WALLET => __('PaymentMethod.Wallet')
        };
    }
}
