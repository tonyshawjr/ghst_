# OAuth Implementation

This directory contains the OAuth 2.0 implementation for ghst_ social media scheduler.

## Structure

```
/api/oauth/
├── authorize.php         # OAuth URL generation endpoint
├── callback/            # Platform-specific OAuth callbacks
│   ├── facebook.php     # Facebook/Instagram OAuth callback
│   ├── twitter.php      # Twitter/X OAuth callback
│   └── linkedin.php     # LinkedIn OAuth callback
└── README.md           # This file
```

## OAuth Flow

1. **Authorization Request**: User clicks "Connect Account" → `authorize.php` generates platform OAuth URL
2. **User Consent**: User redirected to platform, grants permissions
3. **Callback**: Platform redirects to `callback/{platform}.php` with authorization code
4. **Token Exchange**: Callback handler exchanges code for access/refresh tokens
5. **Account Storage**: Tokens and user data stored in database
6. **Automatic Refresh**: Cron job refreshes tokens before expiration

## Security Features

- CSRF protection via state parameter
- Secure token storage in database
- Automatic token refresh before expiration
- Platform-specific security (PKCE for Twitter)
- User authentication required for all endpoints

## Platform Implementation

### Facebook/Instagram
- OAuth 2.0 standard flow
- Manages both user account and Facebook pages
- Long-lived tokens with refresh capability

### Twitter/X
- OAuth 2.0 with PKCE (Proof Key for Code Exchange)
- Enhanced security with code verifier/challenge
- Refresh token support

### LinkedIn
- OAuth 2.0 standard flow
- Professional account access
- Token refresh mechanism

## Configuration

OAuth credentials are configured in `/dashboard/oauth-setup.php` or directly in `config.php`:

```php
// Facebook/Instagram
define('FB_APP_ID', 'your_app_id');
define('FB_APP_SECRET', 'your_app_secret');

// Twitter/X
define('TWITTER_API_KEY', 'your_api_key');
define('TWITTER_API_SECRET', 'your_api_secret');

// LinkedIn
define('LINKEDIN_CLIENT_ID', 'your_client_id');
define('LINKEDIN_CLIENT_SECRET', 'your_client_secret');
```

## Token Refresh

Automatic token refresh is handled by `/cron/refresh-tokens.php`. Add to crontab:

```bash
0 6,12,18,0 * * * /usr/bin/php /path/to/ghst_/cron/refresh-tokens.php
```

This checks for expiring tokens every 6 hours and refreshes them automatically.