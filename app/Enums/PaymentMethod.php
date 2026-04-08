<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash       = 'cash';
    case CreditCard = 'credit_card';
    case DebitCard  = 'debit_card';
    case Pix        = 'pix';
    case Voucher    = 'voucher';

    public function label(): string
    {
        return match($this) {
            self::Cash       => 'Dinheiro',
            self::CreditCard => 'Cartão de crédito',
            self::DebitCard  => 'Cartão de débito',
            self::Pix        => 'Pix',
            self::Voucher    => 'Vale-refeição',
        };
    }

    public function requiresOnlineProcessing(): bool
    {
        return in_array($this, [self::CreditCard, self::DebitCard, self::Pix]);
    }
}
