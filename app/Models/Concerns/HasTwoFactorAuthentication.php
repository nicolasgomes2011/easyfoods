<?php

namespace App\Models\Concerns;

use PragmaRX\Google2FA\Google2FA;

trait HasTwoFactorAuthentication
{
    /**
     * Generate a new two-factor authentication secret.
     */
    public function generateTwoFactorSecret(): string
    {
        $google2fa = new Google2FA;

        $secret = $google2fa->generateSecretKey();

        $this->two_factor_secret = encrypt($secret);
        $this->save();

        return $secret;
    }

    /**
     * Get the decrypted two-factor authentication secret.
     */
    public function getTwoFactorSecret(): ?string
    {
        if (! $this->two_factor_secret) {
            return null;
        }

        return decrypt($this->two_factor_secret);
    }

    /**
     * Enable two-factor authentication for the user.
     */
    public function enableTwoFactorAuthentication(): void
    {
        $this->two_factor_enabled = true;
        $this->save();
    }

    /**
     * Disable two-factor authentication for the user.
     */
    public function disableTwoFactorAuthentication(): void
    {
        $this->two_factor_enabled = false;
        $this->two_factor_secret = null;
        $this->two_factor_recovery_codes = null;
        $this->save();
    }

    /**
     * Verify a two-factor authentication code.
     */
    public function verifyTwoFactorCode(string $code): bool
    {
        $google2fa = new Google2FA;
        $secret = $this->getTwoFactorSecret();

        if (! $secret) {
            return false;
        }

        return $google2fa->verifyKey($secret, $code);
    }

    /**
     * Generate recovery codes for the user.
     */
    public function generateRecoveryCodes(): array
    {
        $codes = [];

        for ($i = 0; $i < 8; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(5)));
        }

        $this->two_factor_recovery_codes = encrypt(json_encode($codes));
        $this->save();

        return $codes;
    }

    /**
     * Get the user's recovery codes.
     */
    public function getRecoveryCodes(): ?array
    {
        if (! $this->two_factor_recovery_codes) {
            return null;
        }

        return json_decode(decrypt($this->two_factor_recovery_codes), true);
    }

    /**
     * Verify a recovery code and remove it after use.
     */
    public function verifyRecoveryCode(string $code): bool
    {
        $codes = $this->getRecoveryCodes();

        if (! $codes) {
            return false;
        }

        $code = strtoupper($code);

        if (in_array($code, $codes)) {
            // Remove the used recovery code
            $codes = array_values(array_diff($codes, [$code]));
            $this->two_factor_recovery_codes = encrypt(json_encode($codes));
            $this->save();

            return true;
        }

        return false;
    }

    /**
     * Get the QR code URL for two-factor authentication.
     */
    public function getTwoFactorQrCodeUrl(): ?string
    {
        $secret = $this->getTwoFactorSecret();

        if (! $secret) {
            return null;
        }

        $google2fa = new Google2FA;
        $appName = config('app.name', 'Laravel');

        return $google2fa->getQRCodeUrl(
            $appName,
            $this->email,
            $secret
        );
    }
}
