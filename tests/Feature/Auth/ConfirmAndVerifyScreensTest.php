<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ConfirmAndVerifyScreensTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_password_screen_is_in_portuguese(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/confirm-password');

        $response->assertOk()
            ->assertSee('Confirmar senha')
            ->assertSee('Esta é uma área protegida. Confirme sua senha para continuar.')
            ->assertSee('Senha')
            ->assertSee('Sua senha')
            ->assertSee('Confirmar')
            ->assertDontSee('Confirm password')
            ->assertDontSee('This is a secure area of the application')
            ->assertDontSee('placeholder="Password"', false)
            ->assertDontSee('>Confirm<', false);
    }

    public function test_wrong_password_shows_the_portuguese_error(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Volt::test('auth.confirm-password')
            ->set('password', 'wrong-password')
            ->call('confirmPassword')
            ->assertHasErrors(['password'])
            ->assertSee('A senha informada está incorreta.')
            ->assertDontSee('The provided password is incorrect.');
    }

    public function test_verify_email_screen_is_in_portuguese(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/verify-email');

        $response->assertOk()
            ->assertSee('Confirme seu e-mail clicando no link que acabamos de enviar para você.')
            ->assertSee('Reenviar e-mail de verificação')
            ->assertSee('Sair')
            ->assertDontSee('Please verify your email address')
            ->assertDontSee('Resend verification email')
            ->assertDontSee('Log out');
    }

    public function test_resending_the_link_shows_the_portuguese_status_and_sends_the_notification(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $this->actingAs($user);

        Volt::test('auth.verify-email')
            ->call('sendVerification')
            ->assertSee('Enviamos um novo link de verificação para o e-mail informado no cadastro.')
            ->assertDontSee('A new verification link has been sent');

        Notification::assertSentTo($user, VerifyEmail::class);
    }
}
