<?php

namespace App\Livewire\Concerns;

use App\Models\DiningTable;
use App\Support\TableMode;
use Livewire\Attributes\Computed;

/**
 * Checkout in table mode (table QR code, /mesa/{uuid}): the order type is
 * dine-in and the table is the one bound to the session — never the payload.
 * Expects $deliveryType, $tableId and a `restaurant` computed property.
 */
trait ChecksOutAtTable
{
    /** Livewire trait hook: runs with the component's mount(). */
    public function mountChecksOutAtTable(): void
    {
        $this->pinSeatedTable();
    }

    /** Table bound by the table QR code, or null outside table mode. */
    #[Computed]
    public function seatedTable(): ?DiningTable
    {
        return TableMode::table($this->restaurant);
    }

    #[Computed]
    public function kiosk(): bool
    {
        return $this->seatedTable !== null && TableMode::isKiosk();
    }

    /** Type and table are fixed by the QR code: client changes are ignored. */
    public function updated(string $property): void
    {
        if (in_array($property, ['deliveryType', 'tableId'], true)) {
            $this->pinSeatedTable();
        }
    }

    /** "Not at the restaurant": back to the normal checkout. Never on the kiosk. */
    public function leaveTableMode(): void
    {
        if (! $this->seatedTable || $this->kiosk) {
            return;
        }

        TableMode::leave();
        unset($this->seatedTable, $this->kiosk);
        $this->deliveryType = 'delivery';
        $this->tableId      = '';
    }

    /**
     * Pins the order to the seated table (no-op outside table mode) and
     * returns why that table can't take an order right now, or null.
     */
    protected function pinSeatedTable(): ?string
    {
        if (! $seated = $this->seatedTable) {
            return null;
        }

        $this->deliveryType = 'dine_in';
        $this->tableId      = (string) $seated->id;

        return TableMode::unavailableReason($seated, $this->restaurant);
    }
}
