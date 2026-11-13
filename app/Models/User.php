<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Concerns\HasTwoFactorAuthentication;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasTwoFactorAuthentication, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'is_available',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_enabled',
    ];

    /** Same default as the column, so a fresh model knows it before being reloaded. */
    protected $attributes = [
        'is_available' => true,
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'  => 'datetime',
            'password'           => 'hashed',
            'two_factor_enabled' => 'boolean',
            'is_active'          => 'boolean',
            // Driver panel: false = on pause, gets no delivery offers.
            'is_available'       => 'boolean',
            'role'               => UserRole::class,
        ];
    }

    public function hasRole(UserRole $role): bool
    {
        return $this->role === $role;
    }

    public function hasAnyRole(UserRole ...$roles): bool
    {
        return in_array($this->role, $roles);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /** Área do painel liberada para o papel do usuário (matriz em UserRole). */
    public function canAccess(string $area): bool
    {
        return $this->role?->canAccess($area) ?? false;
    }

    /** Rota da tela inicial do usuário conforme o papel. */
    public function homeRoute(): string
    {
        return $this->role?->homeRoute() ?? 'admin.dashboard';
    }

    /** Conta ativa (null conta como ativa, igual ao RoleMiddleware). */
    public function isActive(): bool
    {
        return $this->is_active !== false;
    }

    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->map(fn (string $name) => Str::of($name)->substr(0, 1))
            ->implode('');
    }
}
