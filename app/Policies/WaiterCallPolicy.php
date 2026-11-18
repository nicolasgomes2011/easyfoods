<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WaiterCall;

/**
 * Waiter calls are answered from the kitchen screen. Which roles open that
 * screen is decided by the route (role matrix); here only the restaurant is
 * checked: a call of another restaurant is never answered.
 */
class WaiterCallPolicy
{
    public function acknowledge(User $user, WaiterCall $call): bool
    {
        return $user->worksAt($call->restaurant_id);
    }
}
