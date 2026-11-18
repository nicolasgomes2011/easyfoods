<?php

namespace App\Policies;

use App\Models\User;

/** An admin manages only the staff of their own restaurant; anyone may see and edit themselves. */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, User $model): bool
    {
        return $user->id === $model->id || $this->adminOfSameRestaurant($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $model): bool
    {
        return $user->id === $model->id || $this->adminOfSameRestaurant($user, $model);
    }

    public function delete(User $user, User $model): bool
    {
        return $user->id !== $model->id && $this->adminOfSameRestaurant($user, $model);
    }

    private function adminOfSameRestaurant(User $user, User $model): bool
    {
        return $user->isAdmin() && $user->worksAt($model->effectiveRestaurantId());
    }
}
