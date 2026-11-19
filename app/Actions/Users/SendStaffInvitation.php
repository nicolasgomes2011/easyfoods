<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Notifications\StaffInvitation;
use Illuminate\Support\Facades\Password;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class SendStaffInvitation
{
    /**
     * Send (or send again) the invite e-mail: a fresh password-broker token,
     * so the member sets their own password from the link. invited_at marks
     * the member as "Convite pendente" until the first login.
     *
     * Returns whether the e-mail went out (false when the mail transport fails;
     * the account stays and the invite can be sent again from the team screen).
     */
    public function handle(User $user): bool
    {
        $token = Password::broker()->createToken($user);

        $user->forceFill(['invited_at' => now()])->save();

        $restaurantName = $user->restaurant?->name ?? config('app.name');

        try {
            $user->notify(new StaffInvitation($token, $restaurantName));
        } catch (TransportExceptionInterface $e) {
            report($e);

            return false;
        }

        return true;
    }
}
