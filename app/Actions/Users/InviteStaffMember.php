<?php

namespace App\Actions\Users;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\CurrentRestaurant;
use Illuminate\Support\Facades\Password;

class InviteStaffMember
{
    /**
     * Create an inactive staff account and send a password-set link.
     *
     * The new account belongs to the restaurant of who invites (the current
     * restaurant), set on the server — never taken from the form.
     *
     * Returns the reset URL so the admin can share it manually if mail is not configured.
     */
    public function handle(string $name, string $email, UserRole $role): array
    {
        $user = new User([
            'name'      => $name,
            'email'     => $email,
            'password'  => bcrypt(str()->random(32)),
            'role'      => $role,
            'is_active' => true,
        ]);
        $user->forceFill(['restaurant_id' => CurrentRestaurant::id()])->save();

        $status = Password::sendResetLink(['email' => $email]);

        return [
            'user'         => $user,
            'mail_sent'    => $status === Password::RESET_LINK_SENT,
        ];
    }
}
