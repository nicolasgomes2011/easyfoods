<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;

/** Stamps users.last_login_at on every successful login (ends "Convite pendente"). */
class RecordLastLogin
{
    public function handle(Login $event): void
    {
        if ($event->user instanceof User) {
            $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
        }
    }
}
