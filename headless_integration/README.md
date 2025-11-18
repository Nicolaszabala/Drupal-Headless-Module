# Drupal Headless Module

Simplifies headless Drupal setup for modern frontend frameworks like Next.js, React, and Astro.

## Description

Setting up headless Drupal can be complex and time-consuming. This module streamlines the process by providing:

- Auto-configuration of JSON:API and OAuth2 authentication
- Simplified consumer management for frontend applications
- Security best practices out of the box (CORS, rate limiting)
- Developer dashboard with API documentation
- Framework-specific optimizations (extensible)

## Requirements

- Drupal: ^10.3 || ^11
- PHP: >=8.1

### Required Modules

- JSON:API (core)
- [Consumers](https://www.drupal.org/project/consumers) ^1.17
- [Simple OAuth](https://www.drupal.org/project/simple_oauth) ^5.2 || ^6.0

## Installation

### Via Composer (recommended)

```bash
composer require drupal/headless_integration
```

### Manual Installation

1. Download the module and place it in `/modules/contrib/headless_integration`
2. Install dependencies:
   ```bash
   composer require drupal/consumers drupal/simple_oauth
   ```
3. Enable the module:
   ```bash
   drush en headless_integration
   ```

## Configuration

### Initial Setup

1. Navigate to **Configuration > Web Services > Headless Integration Settings**
   (`/admin/config/services/headless-integration`)

2. Configure CORS settings:
   - Enable CORS if your frontend runs on a different domain
   - Add allowed origins (one per line), e.g., `https://example.com`

3. Configure OAuth2 settings:
   - Auto-configure OAuth2 is enabled by default
   - Adjust token expiration as needed (default: 3600 seconds)

4. Optionally enable rate limiting for API protection

### Creating API Consumers

API consumers represent your frontend applications.

#### Via Dashboard

1. Go to **Administration > Headless Dashboard** (`/admin/headless-integration/dashboard`)
2. Click "Manage Consumers"
3. Add a new consumer with:
   - Label: Your app name (e.g., "Next.js Frontend")
   - Description: Purpose of the app
   - Configure roles/permissions as needed

#### Programmatically

```php
$consumer_manager = \Drupal::service('headless_integration.consumer_manager');
$consumer = $consumer_manager->createConsumer(
  'My Next.js App',
  'Production frontend application',
  ['user_id' => 1]
);
```

### Private File System

OAuth2 requires a private file system for storing encryption keys securely.

Add to `settings.php`:

```php
$settings['file_private_path'] = '../private';
```

Create the directory and ensure it's writable:

```bash
mkdir -p ../private
chmod 755 ../private
```

## Usage

### For Next.js

After configuring a consumer, you'll receive:
- Client ID (Consumer UUID)
- Client Secret

Add these to your Next.js `.env.local`:

```env
NEXT_PUBLIC_DRUPAL_BASE_URL=https://your-drupal-site.com
DRUPAL_CLIENT_ID=your-consumer-uuid
DRUPAL_CLIENT_SECRET=your-consumer-secret
```

Use with `next-drupal`:

```javascript
import { DrupalClient } from "next-drupal"

const drupal = new DrupalClient(
  process.env.NEXT_PUBLIC_DRUPAL_BASE_URL,
  {
    auth: {
      clientId: process.env.DRUPAL_CLIENT_ID,
      clientSecret: process.env.DRUPAL_CLIENT_SECRET,
    },
  }
)
```

### For React/Other Frameworks

Authenticate using OAuth2 client credentials flow:

```javascript
// Get access token
const response = await fetch('https://your-drupal-site.com/oauth/token', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/x-www-form-urlencoded',
  },
  body: new URLSearchParams({
    grant_type: 'client_credentials',
    client_id: 'your-consumer-uuid',
    client_secret: 'your-consumer-secret',
  }),
})

const { access_token } = await response.json()

// Use token for API requests
const data = await fetch('https://your-drupal-site.com/jsonapi/node/article', {
  headers: {
    'Authorization': `Bearer ${access_token}`,
  },
})
```

## API Documentation

Your JSON:API is available at: `https://your-site.com/jsonapi`

View available resources: `https://your-site.com/jsonapi/index`

## Permissions

- **Administer Headless Integration**: Full access to configuration
- **Access Headless Dashboard**: View dashboard and API documentation

## Troubleshooting

### CORS Errors

If you get CORS errors in the browser console:

1. Verify CORS is enabled in settings
2. Check that your frontend URL is in the allowed origins list
3. Ensure the URL format is exact (including protocol and no trailing slash)

### OAuth2 Errors

**"Private file system not configured"**
- Add `$settings['file_private_path']` to settings.php
- Ensure the directory exists and is writable

**"Invalid credentials"**
- Verify client ID and secret are correct
- Check that the consumer exists and is active

### Missing Dependencies

Run the status report to verify all required modules are enabled:

```bash
drush status-report
```

## Development

### Running Tests

```bash
# Unit tests
vendor/bin/phpunit modules/contrib/headless_integration/tests/src/Unit

# Kernel tests
vendor/bin/phpunit modules/contrib/headless_integration/tests/src/Kernel

# Functional tests
vendor/bin/phpunit modules/contrib/headless_integration/tests/src/Functional
```

### Code Quality

```bash
# Check coding standards
vendor/bin/phpcs --standard=Drupal,DrupalPractice modules/contrib/headless_integration

# Static analysis
vendor/bin/phpstan analyse modules/contrib/headless_integration
```

## Roadmap

- [ ] Framework-specific submodules (Next.js, React, Astro)
- [ ] Preview mode integration
- [ ] Webhook notifications for cache invalidation
- [ ] GraphQL support
- [ ] Content type scaffolding wizard
- [ ] TypeScript type generation

## Maintainers

This module is community-maintained. Contributions are welcome!

## License

GPL-2.0-or-later

## Support

- [Issue Queue](https://www.drupal.org/project/issues/headless_integration)
- [Documentation](https://www.drupal.org/docs/contributed-modules/headless-integration)
