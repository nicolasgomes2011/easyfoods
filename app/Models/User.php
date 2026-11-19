<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Concerns\HasTwoFactorAuthentication;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    /** effectiveRestaurantId() cache. false = not read yet (null is valid: no restaurant at all). */
    private int|false|null $defaultRestaurantIdMemo = false;

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
            // Tenant: the restaurant this staff member works in (never mass-assigned).
            'restaurant_id'      => 'integer',
            // Staff invitation (never mass-assigned): last invite e-mail and last login.
            'invited_at'         => 'datetime',
            'last_login_at'      => 'datetime',
        ];
    }

    /** Invited from the team screen and never logged in yet. */
    public function isInvitationPending(): bool
    {
        return $this->invited_at !== null && $this->last_login_at === null;
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * The restaurant this staff member works in. Staff not assigned to one yet
     * (restaurant_id null) work in the default restaurant — the lowest id, the
     * same fallback CurrentRestaurant uses for them.
     */
    public function effectiveRestaurantId(): ?int
    {
        if ($this->restaurant_id !== null) {
            return $this->restaurant_id;
        }

        // Read once per instance: the policies ask for it on every row of a list.
        if ($this->defaultRestaurantIdMemo === false) {
            $this->defaultRestaurantIdMemo = self::defaultRestaurantId();
        }

        return $this->defaultRestaurantIdMemo;
    }

    /** Whether a row of $restaurantId belongs to this staff member's restaurant. */
    public function worksAt(int|string|null $restaurantId): bool
    {
        $mine = $this->effectiveRestaurantId();

        return $mine !== null && $restaurantId !== null && (int) $restaurantId === $mine;
    }

    /** Staff of a restaurant, including not-yet-assigned staff when it is the default one. */
    public function scopeOfRestaurant(Builder $query, ?int $restaurantId): Builder
    {
        $isDefault = $restaurantId !== null && $restaurantId === self::defaultRestaurantId();

        return $query->where(function (Builder $query) use ($restaurantId, $isDefault) {
            $query->where('restaurant_id', $restaurantId)
                ->when($isDefault, fn (Builder $query) => $query->orWhereNull('restaurant_id'));
        });
    }

    private static function defaultRestaurantId(): ?int
    {
        $id = Restaurant::query()->orderBy('id')->value('id');

        return $id === null ? null : (int) $id;
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
