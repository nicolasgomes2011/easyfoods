<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\LoginForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use Livewire\Volt\Volt as LivewireVolt;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_factor_challenge_screen_can_be_rendered(): void
    {
        // Session required to access this screen
        Session::put('two_factor_login_id', User::factory()->create()->id);

        $this->get('/two-factor-challenge')->assertStatus(200);
    }

    public function test_user_with_two_factor_enabled_is_redirected_to_challenge(): void
    {
        $user = User::factory()->create();

        $secret = $user->generateTwoFactorSecret();
        $user->enableTwoFactorAuthentication();

        Livewire::test(LoginForm::class)
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('login')
            ->assertRedirect(route('two-factor.challenge', absolute: false));

        $this->assertGuest();
    }

    public function test_user_without_two_factor_enabled_can_login_normally(): void
    {
        $user = User::factory()->create();

        Livewire::test(LoginForm::class)
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_user_can_verify_with_valid_two_factor_code(): void
    {
        $user = User::factory()->create();

        $secret = $user->generateTwoFactorSecret();
        $user->enableTwoFactorAuthentication();

        Session::put('two_factor_login_id', $user->id);
        Session::put('two_factor_remember', false);

        $google2fa = new \PragmaRX\Google2FA\Google2FA;
        $validCode = $google2fa->getCurrentOtp($secret);

        LivewireVolt::test('auth.two-factor-challenge')
            ->set('code', $validCode)
            ->call('verify')
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
        $this->assertFalse(Session::has('two_factor_login_id'));
    }

    public function test_user_cannot_verify_with_invalid_two_factor_code(): void
    {
        $user = User::factory()->create();

        $user->generateTwoFactorSecret();
        $user->enableTwoFactorAuthentication();

        Session::put('two_factor_login_id', $user->id);

        LivewireVolt::test('auth.two-factor-challenge')
            ->set('code', '000000')
            ->call('verify')
            ->assertHasErrors(['code']);

        $this->assertGuest();
    }

    public function test_user_can_verify_with_valid_recovery_code(): void
    {
        $user = User::factory()->create();

        $user->generateTwoFactorSecret();
        $recoveryCodes = $user->generateRecoveryCodes();
        $user->enableTwoFactorAuthentication();

        Session::put('two_factor_login_id', $user->id);
        Session::put('two_factor_remember', false);

        LivewireVolt::test('auth.two-factor-challenge')
            ->set('useRecoveryCode', true)
            ->set('code', $recoveryCodes[0])
            ->call('verify')
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
        $this->assertFalse(Session::has('two_factor_login_id'));

        $user->refresh();
        $remainingCodes = $user->getRecoveryCodes();
        $this->assertCount(7, $remainingCodes);
        $this->assertNotContains($recoveryCodes[0], $remainingCodes);
    }

    public function test_user_cannot_verify_with_invalid_recovery_code(): void
    {
        $user = User::factory()->create();

        $user->generateTwoFactorSecret();
        $user->generateRecoveryCodes();
        $user->enableTwoFactorAuthentication();

        Session::put('two_factor_login_id', $user->id);

        LivewireVolt::test('auth.two-factor-challenge')
            ->set('useRecoveryCode', true)
            ->set('code', 'INVALIDCODE')
            ->call('verify')
            ->assertHasErrors(['code']);

        $this->assertGuest();
    }

    public function test_recovery_code_can_only_be_used_once(): void
    {
        $user = User::factory()->create();

        $user->generateTwoFactorSecret();
        $recoveryCodes = $user->generateRecoveryCodes();
        $user->enableTwoFactorAuthentication();

        $this->assertTrue($user->verifyRecoveryCode($recoveryCodes[0]));
        $this->assertFalse($user->verifyRecoveryCode($recoveryCodes[0]));
    }

    public function test_two_factor_challenge_redirects_to_login_if_no_session(): void
    {
        LivewireVolt::test('auth.two-factor-challenge')
            ->set('code', '123456')
            ->call('verify')
            ->assertRedirect(route('login', absolute: false));
    }
}
