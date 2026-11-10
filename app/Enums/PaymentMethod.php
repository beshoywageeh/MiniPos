<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case CASH = 'cash';
    case BANK_TRANSFER = 'bank_transfer';
    case CREDIT_CARD = 'credit_card';
    case WALLET = 'wallet';

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
