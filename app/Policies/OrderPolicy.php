<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;

/**
 * Every check on a given order also requires the order to belong to the
 * user's restaurant (User::worksAt): another restaurant's order is never allowed.
 */
class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role?->canManageOrders() ?? false;
    }

    public function view(User $user, Order $order): bool
    {
        return $user->worksAt($order->restaurant_id)
            && ($user->role?->canManageOrders() ?? false);
    }

    public function updateStatus(User $user, Order $order): bool
    {
        if (! $user->worksAt($order->restaurant_id) || $order->isInFinalState()) {
            return false;
        }

        return $user->role?->canManageOrders() ?? false;
    }

    public function cancel(User $user, Order $order): bool
    {
        if (! $user->worksAt($order->restaurant_id) || ! $order->isCancelable()) {
            return false;
        }

        return $user->hasAnyRole(UserRole::Admin, UserRole::Manager, UserRole::Attendant);
    }

    /**
     * Register the customer's payment: front-of-house roles, and the driver
     * who collects at the door, only on a delivery assigned to them.
     */
    public function markPaid(User $user, Order $order): bool
    {
        if (! $user->worksAt($order->restaurant_id)) {
            return false;
        }

        if ($user->hasRole(UserRole::Delivery)) {
            return $order->isDelivery()
                && $order->driver_id !== null
                && (int) $order->driver_id === (int) $user->id;
        }

        return $user->hasAnyRole(UserRole::Admin, UserRole::Manager, UserRole::Attendant);
    }

    public function transitionInKitchen(User $user, Order $order): bool
    {
        if (! $user->worksAt($order->restaurant_id) || $order->isInFinalState()) {
            return false;
        }

        return $user->hasAnyRole(UserRole::Admin, UserRole::Manager, UserRole::Attendant, UserRole::Kitchen);
    }

    /** Add or remove items of an open dine-in order (front of house only). */
    public function editItems(User $user, Order $order): bool
    {
        if (! $order->itemsAreEditable()) {
            return false;
        }

        return $this->editItemsAsRole($user, $order);
    }

    /**
     * The role part of editItems only: whether this user may change order items
     * at all. The order screen actions check it first (403), then the order state,
     * which the kitchen may have moved on meanwhile (a message, not a 403).
     */
    public function editItemsAsRole(User $user, Order $order): bool
    {
        return $user->worksAt($order->restaurant_id)
            && $user->hasAnyRole(UserRole::Admin, UserRole::Manager, UserRole::Attendant);
    }
}
