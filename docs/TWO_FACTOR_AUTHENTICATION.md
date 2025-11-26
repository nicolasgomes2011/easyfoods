# Two-Factor Authentication (2FA)

This application includes two-factor authentication (2FA) to add an extra layer of security to user accounts.

## Features

- **Time-based One-Time Password (TOTP)**: Uses Google Authenticator-compatible apps for generating 6-digit codes
- **QR Code Setup**: Easy setup by scanning a QR code with any authenticator app
- **Recovery Codes**: 8 single-use recovery codes provided for account recovery
- **Secure Storage**: Secrets and recovery codes are encrypted in the database
- **User-Friendly Interface**: Clean and intuitive UI built with Livewire and Flux

## User Guide

### Enabling Two-Factor Authentication

1. Log in to your account
2. Navigate to Settings → Two-Factor Auth
3. Click "Enable Two-Factor Authentication"
4. Scan the QR code with your authenticator app (e.g., Google Authenticator, Authy, 1Password)
   - Alternatively, you can manually enter the secret key shown below the QR code
5. Save your recovery codes in a secure location
6. Enter the 6-digit code from your authenticator app to confirm setup
7. Click "Confirm & Enable"

### Logging In with 2FA

1. Enter your email and password on the login page
2. You'll be redirected to the two-factor challenge page
3. Enter the 6-digit code from your authenticator app
4. Click "Verify" to complete login

### Using Recovery Codes

If you don't have access to your authenticator app:

1. On the two-factor challenge page, click "Use a recovery code"
2. Enter one of your recovery codes
3. Click "Verify"

**Important**: Each recovery code can only be used once. After using a recovery code, make sure to regenerate new ones.

### Managing Recovery Codes

To view or regenerate recovery codes:

1. Go to Settings → Two-Factor Auth
2. Click "Show Recovery Codes" to view your current codes
3. Click "Regenerate Recovery Codes" to generate new codes
   - This will invalidate all previous recovery codes

### Disabling Two-Factor Authentication

1. Navigate to Settings → Two-Factor Auth
2. Click "Disable Two-Factor Authentication"
3. Your 2FA settings will be removed from your account

## Technical Details

### Database Schema

Three new columns are added to the `users` table:

- `two_factor_secret` (text, nullable): Encrypted TOTP secret
- `two_factor_recovery_codes` (text, nullable): Encrypted JSON array of recovery codes
- `two_factor_enabled` (boolean, default: false): Whether 2FA is active

### Authentication Flow

1. User submits email and password
2. System validates credentials
3. If 2FA is enabled:
   - User ID is stored in session
   - User is redirected to 2FA challenge page
   - User must verify with TOTP code or recovery code
4. If 2FA is disabled:
   - User is logged in directly

### Security Features

- Secrets and recovery codes are encrypted using Laravel's encryption
- Rate limiting prevents brute force attacks (5 attempts per IP)
- Recovery codes are single-use and automatically removed after verification
- Session-based temporary storage during 2FA challenge

### Dependencies

- `pragmarx/google2fa-laravel`: TOTP implementation compatible with Google Authenticator
- `simplesoftwareio/simple-qrcode`: QR code generation for easy setup

## Testing

The implementation includes comprehensive test coverage:

- 9 authentication flow tests
- 7 settings management tests

Run tests with:

```bash
php artisan test --filter=TwoFactor
```

Or run all tests:

```bash
php artisan test
```

## Supported Authenticator Apps

Any TOTP-compatible authenticator app will work, including:

- Google Authenticator (iOS, Android)
- Microsoft Authenticator (iOS, Android)
- Authy (iOS, Android, Desktop)
- 1Password (iOS, Android, Desktop, Browser)
- Bitwarden (iOS, Android, Desktop, Browser)
- LastPass Authenticator (iOS, Android)

## Troubleshooting

### "The provided code is invalid" error

- Ensure your device's time is synchronized correctly
- TOTP codes are time-sensitive and expire every 30 seconds
- Try waiting for a new code to be generated

### Lost access to authenticator app

- Use one of your recovery codes to log in
- Once logged in, disable 2FA and re-enable it with a new device

### Lost recovery codes

- If you still have access to your authenticator app, log in and regenerate new codes
- If you don't have access to either, contact support

## Security Best Practices

1. **Store recovery codes securely**: Keep them in a password manager or secure physical location
2. **Enable 2FA on all important accounts**: Use 2FA wherever available
3. **Use a reputable authenticator app**: Choose well-known, regularly updated apps
4. **Back up your authenticator**: Some apps support cloud backup or export
5. **Regenerate recovery codes**: After using recovery codes, generate new ones
