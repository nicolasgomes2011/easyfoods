<?php

namespace App\Actions\Users;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Password;

class InviteStaffMember
{
    /**
     * Create an inactive staff account and send a password-set link.
     *
     * Returns the reset URL so the admin can share it manually if mail is not configured.
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
