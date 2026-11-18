<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

/** Checks on a given product also require it to belong to the user's restaurant. */
class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role?->canManageCatalog() ?? false;
    }

    public function view(User $user, Product $product): bool
    {
        return $user->worksAt($product->restaurant_id)
            && ($user->role?->canManageCatalog() ?? false);
    }

    public function create(User $user): bool
    {
        return $user->role?->canManageCatalog() ?? false;
    }

    public function update(User $user, Product $product): bool
    {
        return $user->worksAt($product->restaurant_id)
            && ($user->role?->canManageCatalog() ?? false);
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->worksAt($product->restaurant_id)
            && ($user->role?->canManageCatalog() ?? false);
    }
}
