<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Invite e-mail for a new staff member. The button opens the set-password
 * screen with a token of the password broker (the same screen and token as
 * "Esqueci minha senha", so the link expires like a reset link).
 */
class StaffInvitation extends Notification
{
    public function __construct(
        public readonly string $token,
        public readonly string $restaurantName,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        $minutes = config('auth.passwords.' . config('auth.defaults.passwords') . '.expire');
        $role    = $notifiable->role?->label() ?? 'Funcionário';

        return (new MailMessage)
            ->subject("Convite para a equipe de {$this->restaurantName}")
            ->greeting("Olá, {$notifiable->name}!")
            ->line("Você foi convidado(a) para a equipe de {$this->restaurantName} no EasyFoods, na função {$role}.")
            ->line('Para entrar no painel, defina a sua senha:')
            ->action('Definir minha senha', $url)
            ->line("O link vale por {$minutes} minutos. Se ele expirar, peça ao administrador para reenviar o convite.")
            ->salutation('Equipe EasyFoods');
    }
}
