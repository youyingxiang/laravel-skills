# Configuration Guide

Complete guide for configuring WhatsApp template notifications in your Laravel application.

## Prerequisites

Before configuring, ensure you have:

1. **WhatsApp Business Account**: Access to WhatsApp Business Manager or Meta Business Suite
2. **API Credentials**: 
   - Phone Number ID
   - Access Token
   - Business Account ID (optional)
3. **Approved Templates**: At least one WhatsApp template approved in your Business Account

## Step 1: Install Dependencies

Install the WhatsApp Cloud API package:

```bash
composer require netflie/whatsapp-cloud-api
```

**Note**: Choose a version compatible with your Laravel and PHP version. Check the package's documentation for version compatibility.

## Step 2: Environment Variables

Add the following to your `.env` file:

```env
WHATSAPP_PHONE_NUMBER_ID=your_phone_number_id_here
WHATSAPP_ACCESS_TOKEN=your_access_token_here
WHATSAPP_BUSINESS_ACCOUNT_ID=your_business_account_id_here
```

### Getting Your Credentials

1. **Phone Number ID**: 
   - Go to Meta Business Suite
   - Navigate to WhatsApp > API Setup
   - Find your Phone Number ID

2. **Access Token**:
   - In Meta Business Suite, go to WhatsApp > API Setup
   - Generate a temporary or permanent access token
   - **Security**: Use environment variables, never commit tokens to version control

3. **Business Account ID**:
   - Found in Meta Business Suite under Account Settings
   - Optional for basic functionality

## Step 3: Configuration File

Add WhatsApp configuration to `config/services.php`:

```php
<?php

return [
    // ... other services ...

    'whatsapp' => [
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
    ],
];
```

See `assets/config-template.php` for a complete example with comments.

## Step 4: Copy Channel Class

1. Copy `scripts/WhatsAppChannel.php` to `app/Channels/WhatsAppChannel.php`
2. Update the namespace if needed (should be `App\Channels` by default)

## Step 5: Register the Channel

Register the WhatsApp channel in your service provider. The method varies slightly by Laravel version:

### Laravel 9, 10, 11+

In `app/Providers/AppServiceProvider.php`:

```php
<?php

namespace App\Providers;

use App\Channels\WhatsAppChannel;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Register WhatsApp notification channel
        Notification::extend('whatsapp', function ($app) {
            return new WhatsAppChannel();
        });
    }
}
```

### Alternative: Using a Service Provider Method

You can also register in the `boot()` method:

```php
public function boot()
{
    Notification::extend('whatsapp', function ($app) {
        return new WhatsAppChannel();
    });
}
```

## Step 6: Verify Configuration

Test your configuration by checking if the config values are loaded:

```php
// In tinker or a test route
config('services.whatsapp.phone_number_id');
config('services.whatsapp.access_token');
```

Both should return your configured values (not null).

## Troubleshooting

### Configuration Not Loading

- Clear config cache: `php artisan config:clear`
- Verify `.env` file is in the project root
- Check that `.env` values don't have quotes around them

### Missing Credentials

- Verify all environment variables are set
- Check for typos in variable names
- Ensure you're using the correct credentials from Meta Business Suite

### Channel Not Found

- Verify the channel is registered in your service provider
- Clear application cache: `php artisan cache:clear`
- Check that `AppServiceProvider` is registered in `config/app.php`

## Security Best Practices

1. **Never commit credentials**: Always use environment variables
2. **Use different tokens for different environments**: Dev, staging, production
3. **Rotate tokens regularly**: Especially if they're exposed
4. **Limit token permissions**: Only grant necessary permissions
5. **Monitor API usage**: Set up alerts for unusual activity

## Next Steps

After configuration:
1. Create your first notification class (see `references/USAGE.md`)
2. Test sending a notification
3. Set up error handling and logging
