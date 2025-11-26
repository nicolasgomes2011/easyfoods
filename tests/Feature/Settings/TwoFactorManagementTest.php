<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt as LivewireVolt;
use Tests\TestCase;

class TwoFactorManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_factor_settings_page_can_be_rendered(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/settings/two-factor');

        $response->assertStatus(200);
    }

    public function test_user_can_enable_two_factor_authentication(): void
    {
        $user = User::factory()->create();

        $response = LivewireVolt::actingAs($user)
            ->test('settings.two-factor')
            ->call('enableTwoFactor');

        $response->assertSet('showingQrCode', true);
        $response->assertSet('showingRecoveryCodes', true);

        $user->refresh();
        $this->assertNotNull($user->two_factor_secret);
        $this->assertNotNull($user->two_factor_recovery_codes);
        $this->assertFalse($user->two_factor_enabled); // Not enabled until confirmed
    }

    public function test_user_can_confirm_and_enable_two_factor_authentication(): void
    {
        $user = User::factory()->create();

        // Enable 2FA first
        $component = LivewireVolt::actingAs($user)
            ->test('settings.two-factor')
            ->call('enableTwoFactor');

        $user->refresh();
        $secret = $user->getTwoFactorSecret();

        // Generate a valid code
        $google2fa = new \PragmaRX\Google2FA\Google2FA;
        $validCode = $google2fa->getCurrentOtp($secret);

        $component
            ->set('confirmationCode', $validCode)
            ->call('confirmTwoFactor');

        $user->refresh();
        $this->assertTrue($user->two_factor_enabled);
    }

    public function test_user_cannot_confirm_with_invalid_code(): void
    {
        $user = User::factory()->create();

        // Enable 2FA first
        $component = LivewireVolt::actingAs($user)
            ->test('settings.two-factor')
            ->call('enableTwoFactor');

        $component
            ->set('confirmationCode', '000000')
            ->call('confirmTwoFactor')
            ->assertHasErrors(['confirmationCode']);

        $user->refresh();
        $this->assertFalse($user->two_factor_enabled);
    }

    public function test_user_can_disable_two_factor_authentication(): void
    {
        $user = User::factory()->create();

        // Enable 2FA
        $user->generateTwoFactorSecret();
        $user->generateRecoveryCodes();
        $user->enableTwoFactorAuthentication();

        $this->assertTrue($user->two_factor_enabled);

        LivewireVolt::actingAs($user)
            ->test('settings.two-factor')
            ->call('disableTwoFactor');

        $user->refresh();
        $this->assertFalse($user->two_factor_enabled);
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_recovery_codes);
    }

    public function test_user_can_view_recovery_codes(): void
    {
        $user = User::factory()->create();

        // Enable 2FA
        $user->generateTwoFactorSecret();
        $recoveryCodes = $user->generateRecoveryCodes();
        $user->enableTwoFactorAuthentication();

        $response = LivewireVolt::actingAs($user)
            ->test('settings.two-factor')
            ->call('showRecoveryCodes');

        $response->assertSet('showingRecoveryCodes', true);
        $response->assertSet('recoveryCodes', $recoveryCodes);
    }

    public function test_user_can_regenerate_recovery_codes(): void
    {
        $user = User::factory()->create();

        // Enable 2FA
        $user->generateTwoFactorSecret();
        $originalCodes = $user->generateRecoveryCodes();
        $user->enableTwoFactorAuthentication();

        $response = LivewireVolt::actingAs($user)
            ->test('settings.two-factor')
            ->call('regenerateRecoveryCodes');

        $user->refresh();
        $newCodes = $user->getRecoveryCodes();

        $this->assertCount(8, $newCodes);
        $this->assertNotEquals($originalCodes, $newCodes);
    }
}
