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

    /**
     * Matriz de acesso do painel: área => papéis que podem abrir.
     * Fonte única usada pelas rotas (routes/admin.php) e pelo menu lateral.
     *
     * @return list<self>
     */
    public static function allowedFor(string $area): array
    {
        return match ($area) {
            'dashboard', 'orders', 'dining', 'customers' => [self::Admin, self::Manager, self::Attendant],
            'kitchen'                        => [self::Admin, self::Manager, self::Attendant, self::Kitchen],
            'driver'                         => [self::Admin, self::Manager, self::Delivery],
            'catalog', 'settings', 'reports' => [self::Admin, self::Manager],
            'users'                          => [self::Admin],
        };
    }

    public function canAccess(string $area): bool
    {
        return in_array($this, self::allowedFor($area), true);
    }

    /** Middleware `role:` da área, ex. `role:admin,manager`. */
    public static function middleware(string $area): string
    {
        return 'role:' . implode(',', array_map(fn (self $role) => $role->value, self::allowedFor($area)));
    }

    /** Nome da rota da tela inicial do papel (para onde o login leva). */
    public function homeRoute(): string
    {
        return match ($this) {
            self::Kitchen  => 'admin.kitchen.index',
            self::Delivery => 'admin.driver.index',
            default        => 'admin.dashboard',
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
