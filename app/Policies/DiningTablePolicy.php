<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\DiningTable;
use App\Models\User;

/**
 * Dining room. The roles of the `dining` area (UserRole::allowedFor) run the
 * room: table status, closing a tab, the waitlist. Only admin and manager
 * create, edit or delete a table. Checks on a given table also require it to
 * belong to the user's restaurant.
 */
class DiningTablePolicy
{
    /** Run the room: occupy/free/reserve a table, close a tab, use the waitlist. */
    public function operate(User $user, ?DiningTable $table = null): bool
    {
        if ($table !== null && ! $user->worksAt($table->restaurant_id)) {
            return false;
        }

        return $user->role?->canAccess('dining') ?? false;
    }

    public function create(User $user): bool
    {
        return $this->managesTables($user);
    }

    public function update(User $user, DiningTable $table): bool
    {
        return $user->worksAt($table->restaurant_id) && $this->managesTables($user);
    }

    public function delete(User $user, DiningTable $table): bool
    {
        return $user->worksAt($table->restaurant_id) && $this->managesTables($user);
    }

    private function managesTables(User $user): bool
    {
        return $user->hasAnyRole(UserRole::Admin, UserRole::Manager);
    }
}
