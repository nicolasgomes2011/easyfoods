<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Two-factor challenge screen in Portuguese (EF-67): only the texts change,
 * the 2FA logic is covered by TwoFactorAuthenticationTest.
 */
class TwoFactorChallengeScreenTest extends TestCase
{
    use RefreshDatabase;

    private function userWithTwoFactor(): User
    {
        $user = User::factory()->create();
        $user->generateTwoFactorSecret();
        $user->generateRecoveryCodes();
        $user->enableTwoFactorAuthentication();

        return $user;
    }

    public function test_challenge_screen_is_in_portuguese(): void
    {
        Session::put('two_factor_login_id', $this->userWithTwoFactor()->id);

        $this->get('/two-factor-challenge')
            ->assertOk()
            ->assertSee('Verificação em duas etapas')
            ->assertSee('Digite o código de 6 dígitos do seu aplicativo autenticador.')
            ->assertSee('Código de autenticação')
            ->assertSee('Verificar')
            ->assertSee('Usar um código de recuperação')
            ->assertSee('Voltar para o login')
            ->assertDontSee('Two-Factor Authentication')
            ->assertDontSee('Enter the code from your authenticator app')
            ->assertDontSee('Authentication Code')
            ->assertDontSee('>Verify<', false)
            ->assertDontSee('Use a recovery code')
            ->assertDontSee('Back to login')
            ->assertDontSee('Undefined');
    }

    public function test_recovery_code_mode_is_in_portuguese(): void
    {
        Session::put('two_factor_login_id', $this->userWithTwoFactor()->id);

        Volt::test('auth.two-factor-challenge')
            ->call('toggleRecoveryCode')
            ->assertSee('Digite um dos seus códigos de recuperação.')
            ->assertSee('Código de recuperação')
            ->assertSee('Usar o código do aplicativo autenticador')
            ->assertDontSee('Enter one of your recovery codes')
            ->assertDontSee('Recovery Code')
            ->assertDontSee('Use authentication code');
    }

    public function test_invalid_code_shows_the_portuguese_error(): void
    {
        Session::put('two_factor_login_id', $this->userWithTwoFactor()->id);

        Volt::test('auth.two-factor-challenge')
            ->set('code', '000000')
            ->call('verify')
            ->assertHasErrors(['code'])
            ->assertSee('O código informado é inválido.')
            ->assertDontSee('The provided code is invalid.');
    }

    public function test_too_many_attempts_shows_the_portuguese_error(): void
    {
        $user = $this->userWithTwoFactor();
        Session::put('two_factor_login_id', $user->id);

        $key = 'two-factor-'.$user->id.'|127.0.0.1';
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit($key);
        }

        Volt::test('auth.two-factor-challenge')
            ->set('code', '000000')
            ->call('verify')
            ->assertHasErrors(['code'])
            ->assertSee('Muitas tentativas. Tente novamente em')
            ->assertDontSee('Too many attempts');
    }
}
