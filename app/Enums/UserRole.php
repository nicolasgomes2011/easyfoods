<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Attendant = 'attendant';
    case Kitchen = 'kitchen';
    case Delivery = 'delivery';

    public function label(): string
    {
        return match($this) {
            self::Admin     => 'Administrador',
            self::Manager   => 'Gerente',
            self::Attendant => 'Atendente',
            self::Kitchen   => 'Cozinha',
            self::Delivery  => 'Entregador',
        };
    }

    public function canAccessAdmin(): bool
    {
        return match($this) {
            self::Admin, self::Manager, self::Attendant, self::Kitchen, self::Delivery => true,
        };
    }

    public function canManageCatalog(): bool
    {
        return in_array($this, [self::Admin, self::Manager]);
    }

    public function canManageOrders(): bool
    {
        return in_array($this, [self::Admin, self::Manager, self::Attendant]);
    }

    public function canManageUsers(): bool
    {
        return in_array($this, [self::Admin]);
    }

    public function canManageSettings(): bool
    {
        return in_array($this, [self::Admin, self::Manager]);
    }
}
