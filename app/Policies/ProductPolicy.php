<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role?->canManageCatalog() ?? false;
    }

    public function view(User $user, Product $product): bool
    {
        return $user->role?->canManageCatalog() ?? false;
    }

    public function create(User $user): bool
    {
        return $user->role?->canManageCatalog() ?? false;
    }

    public function update(User $user, Product $product): bool
    {
        return $user->role?->canManageCatalog() ?? false;
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->role?->canManageCatalog() ?? false;
    }
}
