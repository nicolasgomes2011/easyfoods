<?php

namespace App\Actions\Users;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\CurrentRestaurant;

class InviteStaffMember
{
    public function __construct(private SendStaffInvitation $sendInvitation) {}

    /**
     * Create an active staff account with a random password and send the
     * invite e-mail (StaffInvitation), whose link lets the new member choose
     * their own password.
     *
     * The new account belongs to the restaurant of who invites (the current
     * restaurant), set on the server — never taken from the form.
     *
     * Returns the created user and whether the invite e-mail was sent.
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

        return [
            'user'      => $user,
            'mail_sent' => $this->sendInvitation->handle($user),
        ];
    }
}
