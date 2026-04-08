<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Draft              = 'draft';
    case PendingConfirmation = 'pending_confirmation';
    case Confirmed          = 'confirmed';
    case InPreparation      = 'in_preparation';
    case ReadyForPickup     = 'ready_for_pickup';
    case OutForDelivery     = 'out_for_delivery';
    case Delivered          = 'delivered';
    case Completed          = 'completed';
    case Canceled           = 'canceled';

    public function label(): string
    {
        return match($this) {
            self::Draft               => 'Rascunho',
            self::PendingConfirmation => 'Aguardando confirmação',
            self::Confirmed           => 'Confirmado',
            self::InPreparation       => 'Em preparo',
            self::ReadyForPickup      => 'Pronto para retirada',
            self::OutForDelivery      => 'Saiu para entrega',
            self::Delivered           => 'Entregue',
            self::Completed           => 'Concluído',
            self::Canceled            => 'Cancelado',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Completed, self::Canceled]);
    }

    public function isActive(): bool
    {
        return ! $this->isFinal() && $this !== self::Draft;
    }

    /**
     * Returns the allowed next statuses from the current one.
     *
     * @return array<OrderStatus>
     */
    public function allowedTransitions(): array
    {
        return match($this) {
            self::Draft               => [self::PendingConfirmation, self::Canceled],
            self::PendingConfirmation => [self::Confirmed, self::Canceled],
            self::Confirmed           => [self::InPreparation, self::Canceled],
            self::InPreparation       => [self::ReadyForPickup, self::Canceled],
            self::ReadyForPickup      => [self::OutForDelivery, self::Delivered, self::Canceled],
            self::OutForDelivery      => [self::Delivered, self::Canceled],
            self::Delivered           => [self::Completed],
            self::Completed           => [],
            self::Canceled            => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions());
    }

    public function color(): string
    {
        return match($this) {
            self::Draft               => 'gray',
            self::PendingConfirmation => 'yellow',
            self::Confirmed           => 'blue',
            self::InPreparation       => 'orange',
            self::ReadyForPickup      => 'purple',
            self::OutForDelivery      => 'indigo',
            self::Delivered           => 'teal',
            self::Completed           => 'green',
            self::Canceled            => 'red',
        };
    }
}
