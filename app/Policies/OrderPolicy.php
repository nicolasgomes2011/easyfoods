<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role?->canManageOrders() ?? false;
    }

    public function view(User $user, Order $order): bool
    {
        return $user->role?->canManageOrders() ?? false;
    }

    public function updateStatus(User $user, Order $order): bool
    {
        if ($order->isInFinalState()) {
            return false;
        }

        return $user->role?->canManageOrders() ?? false;
    }

    public function cancel(User $user, Order $order): bool
    {
        if (! $order->isCancelable()) {
            return false;
        }

        return $user->hasAnyRole(UserRole::Admin, UserRole::Manager, UserRole::Attendant);
    }
}
