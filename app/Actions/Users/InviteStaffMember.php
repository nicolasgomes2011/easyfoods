<?php

namespace App\Actions\Users;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Password;

class InviteStaffMember
{
    /**
     * Create an active staff account with a random password and send a
     * password-set link, so the new member chooses their own password.
     *
     * Returns the created user and whether the link e-mail was sent.
     */
    public function handle(string $name, string $email, UserRole $role): array
    {
        $user = User::create([
            'name'      => $name,
            'email'     => $email,
            'password'  => bcrypt(str()->random(32)),
            'role'      => $role,
            'is_active' => true,
        ]);

        $status = Password::sendResetLink(['email' => $email]);

        return [
            'user'         => $user,
            'mail_sent'    => $status === Password::RESET_LINK_SENT,
        ];
    }
}
