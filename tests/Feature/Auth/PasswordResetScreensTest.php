<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PasswordResetScreensTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_screen_is_in_portuguese_with_easyfoods_branding(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertOk()
            ->assertSee('<title>EasyFoods</title>', false)
            ->assertSee('EasyFoods')
            ->assertSee('Esqueci minha senha')
            ->assertSee('Informe seu e-mail para receber o link de redefinição')
            ->assertSee('E-mail')
            ->assertSee('seu@email.com')
            ->assertSee('Enviar link de redefinição')
            ->assertSee('Ou volte para o')
            ->assertSee('login')
            ->assertDontSee('<title>Laravel</title>', false)
            ->assertDontSee('Forgot password')
            ->assertDontSee('Enter your email to receive a password reset link')
            ->assertDontSee('Email password reset link')
            ->assertDontSee('email@example.com')
            ->assertDontSee('Or, return to');
    }

    public function test_requesting_a_link_shows_the_portuguese_status_and_sends_the_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        Volt::test('auth.forgot-password')
            ->set('email', $user->email)
            ->call('sendPasswordResetLink')
            ->assertHasNoErrors()
            ->assertSee('Se a conta existir, enviaremos um link de redefinição.')
            ->assertDontSee('A reset link will be sent if the account exists.');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_unknown_email_gets_the_same_status_without_a_notification(): void
    {
        Notification::fake();

        Volt::test('auth.forgot-password')
            ->set('email', 'ninguem@exemplo.com')
            ->call('sendPasswordResetLink')
            ->assertHasNoErrors()
            ->assertSee('Se a conta existir, enviaremos um link de redefinição.');

        Notification::assertNothingSent();
    }

    public function test_reset_password_screen_is_in_portuguese(): void
    {
        $response = $this->get('/reset-password/some-token?email=cliente@exemplo.com');

        $response->assertOk()
            ->assertSee('<title>EasyFoods</title>', false)
            ->assertSee('Redefinir senha')
            ->assertSee('Digite sua nova senha abaixo')
            ->assertSee('Nova senha')
            ->assertSee('Confirmar nova senha')
            ->assertDontSee('Reset password')
            ->assertDontSee('Please enter your new password below')
            ->assertDontSee('Confirm password');
    }
}
