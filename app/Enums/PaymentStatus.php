<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending           = 'pending';
    case Authorized        = 'authorized';
    case Paid              = 'paid';
    case Failed            = 'failed';
    case Refunded          = 'refunded';
    case PartiallyRefunded = 'partially_refunded';
    case Canceled          = 'canceled';

    public function label(): string
    {
        return match($this) {
            self::Pending           => 'Pendente',
            self::Authorized        => 'Autorizado',
            self::Paid              => 'Pago',
            self::Failed            => 'Falhou',
            self::Refunded          => 'Estornado',
            self::PartiallyRefunded => 'Parcialmente estornado',
            self::Canceled          => 'Cancelado',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Paid, self::Refunded, self::Canceled]);
    }

    public function isPaid(): bool
    {
        return $this === self::Paid;
    }

    public function allowedTransitions(): array
    {
        return match($this) {
            self::Pending           => [self::Authorized, self::Paid, self::Failed, self::Canceled],
            self::Authorized        => [self::Paid, self::Failed, self::Canceled],
            self::Paid              => [self::Refunded, self::PartiallyRefunded],
            self::Failed            => [self::Pending],
            self::Refunded          => [],
            self::PartiallyRefunded => [self::Refunded],
            self::Canceled          => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions());
    }
}
