<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Livewire\Volt\Volt as LivewireVolt;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_factor_challenge_screen_can_be_rendered(): void
    {
        $response = $this->get('/two-factor-challenge');

        $response->assertStatus(200);
    }

    public function test_user_with_two_factor_enabled_is_redirected_to_challenge(): void
    {
        $user = User::factory()->create();

        // Enable 2FA
        $secret = $user->generateTwoFactorSecret();
        $user->enableTwoFactorAuthentication();

        $response = LivewireVolt::test('auth.login')
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('login');

        $response->assertRedirect(route('two-factor.challenge', absolute: false));
        $this->assertGuest();
    }

    public function test_user_without_two_factor_enabled_can_login_normally(): void
    {
        $user = User::factory()->create();

        $response = LivewireVolt::test('auth.login')
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('login');

        $response
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_user_can_verify_with_valid_two_factor_code(): void
    {
        $user = User::factory()->create();

        // Enable 2FA
        $secret = $user->generateTwoFactorSecret();
        $user->enableTwoFactorAuthentication();

        // Simulate login session
        Session::put('two_factor_login_id', $user->id);
        Session::put('two_factor_remember', false);

        // Generate a valid code
        $google2fa = new \PragmaRX\Google2FA\Google2FA;
        $validCode = $google2fa->getCurrentOtp($secret);

        $response = LivewireVolt::test('auth.two-factor-challenge')
            ->set('code', $validCode)
            ->call('verify');

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();
        $this->assertFalse(Session::has('two_factor_login_id'));
    }

    public function test_user_cannot_verify_with_invalid_two_factor_code(): void
    {
        $user = User::factory()->create();

        // Enable 2FA
        $user->generateTwoFactorSecret();
        $user->enableTwoFactorAuthentication();

        // Simulate login session
        Session::put('two_factor_login_id', $user->id);

        $response = LivewireVolt::test('auth.two-factor-challenge')
            ->set('code', '000000')
            ->call('verify');

        $response->assertHasErrors(['code']);
        $this->assertGuest();
    }

    public function test_user_can_verify_with_valid_recovery_code(): void
    {
        $user = User::factory()->create();

        // Enable 2FA
        $user->generateTwoFactorSecret();
        $recoveryCodes = $user->generateRecoveryCodes();
        $user->enableTwoFactorAuthentication();

        // Simulate login session
        Session::put('two_factor_login_id', $user->id);
        Session::put('two_factor_remember', false);

        $response = LivewireVolt::test('auth.two-factor-challenge')
            ->set('useRecoveryCode', true)
            ->set('code', $recoveryCodes[0])
            ->call('verify');

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();
        $this->assertFalse(Session::has('two_factor_login_id'));

        // Verify the recovery code was removed
        $user->refresh();
        $remainingCodes = $user->getRecoveryCodes();
        $this->assertCount(7, $remainingCodes);
        $this->assertNotContains($recoveryCodes[0], $remainingCodes);
    }

    public function test_user_cannot_verify_with_invalid_recovery_code(): void
    {
        $user = User::factory()->create();

        // Enable 2FA
        $user->generateTwoFactorSecret();
        $user->generateRecoveryCodes();
        $user->enableTwoFactorAuthentication();

        // Simulate login session
        Session::put('two_factor_login_id', $user->id);

        $response = LivewireVolt::test('auth.two-factor-challenge')
            ->set('useRecoveryCode', true)
            ->set('code', 'INVALIDCODE')
            ->call('verify');

        $response->assertHasErrors(['code']);
        $this->assertGuest();
    }

    public function test_recovery_code_can_only_be_used_once(): void
    {
        $user = User::factory()->create();

        // Enable 2FA
        $user->generateTwoFactorSecret();
        $recoveryCodes = $user->generateRecoveryCodes();
        $user->enableTwoFactorAuthentication();

        // Use the first recovery code
        $this->assertTrue($user->verifyRecoveryCode($recoveryCodes[0]));

        // Try to use the same code again
        $this->assertFalse($user->verifyRecoveryCode($recoveryCodes[0]));
    }

    public function test_two_factor_challenge_redirects_to_login_if_no_session(): void
    {
        $response = LivewireVolt::test('auth.two-factor-challenge')
            ->set('code', '123456')
            ->call('verify');

        $response->assertRedirect(route('login', absolute: false));
    }
}
