---
name: whatsapp-template-notification
description: Send WhatsApp template messages via Laravel notifications using WhatsApp Cloud API. Use this skill when you need to send structured template messages to users through WhatsApp, such as order confirmations, notifications, alerts, or any business communication that requires WhatsApp template messaging.
license: MIT
compatibility: Laravel framework, requires netflie/whatsapp-cloud-api package (version compatible with your Laravel/PHP version)
---

# WhatsApp Template Notification

Send WhatsApp template messages in Laravel applications using the WhatsApp Cloud API. This skill provides a Laravel notification channel that integrates with the `netflie/whatsapp-cloud-api` package to send template-based messages.

## When to Use This Skill

Use this skill when you need to:
- Send structured WhatsApp messages to users
- Integrate WhatsApp notifications into your Laravel application
- Send business notifications, alerts, or confirmations via WhatsApp
- Use WhatsApp Cloud API template messages

## Prerequisites

1. **WhatsApp Business Account**: You need a WhatsApp Business Account with access to WhatsApp Cloud API
2. **Composer Package**: Install `netflie/whatsapp-cloud-api` package (choose version compatible with your Laravel/PHP version)
3. **API Credentials**: Obtain your WhatsApp API credentials:
   - Phone Number ID
   - Access Token
   - Business Account ID (optional)

## Installation

### Step 1: Install Dependencies

Install the WhatsApp Cloud API package via Composer:

```bash
composer require netflie/whatsapp-cloud-api
```

Choose a version compatible with your Laravel and PHP version. Check the package documentation for version compatibility.

### Step 2: Configure Environment Variables

Add the following to your `.env` file:

```env
WHATSAPP_PHONE_NUMBER_ID=your_phone_number_id
WHATSAPP_ACCESS_TOKEN=your_access_token
WHATSAPP_BUSINESS_ACCOUNT_ID=your_business_account_id
```

### Step 3: Add Configuration

Add WhatsApp configuration to `config/services.php`:

```php
'whatsapp' => [
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
    'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
],
```

### Step 4: Copy Channel Class

Copy `scripts/WhatsAppChannel.php` to your `app/Channels/` directory and update the namespace to match your application.

### Step 5: Register the Channel

In your service provider (e.g., `AppServiceProvider`), register the WhatsApp channel:

```php
use Illuminate\Support\Facades\Notification;
use App\Channels\WhatsAppChannel;

public function register()
{
    Notification::extend('whatsapp', function ($app) {
        return new WhatsAppChannel();
    });
}
```

## Usage

### Creating a Notification

Create a notification class that implements the `toWhatsApp()` method:

```php
<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Netflie\WhatsAppCloudApi\Message\Template\Component;

class ExampleNotification extends Notification
{
    public function via($notifiable)
    {
        return ['whatsapp'];
    }

    public function toWhatsApp($notifiable)
    {
        return [
            'to' => $notifiable->mobile_no, // Phone number in international format
            'template_name' => 'your_template_name', // Template name approved in WhatsApp Business
            'language' => 'en', // Template language code
            'components' => new Component(
                [], // Header components (optional)
                [   // Body components
                    [
                        'type' => 'text',
                        'text' => 'Your dynamic text here',
                    ],
                ],
                []  // Button components (optional)
            )
        ];
    }
}
```

### Sending Notifications

Send notifications to users:

```php
use App\Notifications\ExampleNotification;

$user->notify(new ExampleNotification($data));
```

Or send to multiple users:

```php
Notification::send($users, new ExampleNotification($data));
```

## Template Components

WhatsApp templates support three types of components:

1. **Header**: Optional header component (images, videos, documents, or text)
2. **Body**: Required body component with dynamic text parameters
3. **Buttons**: Optional buttons (quick reply, URL, or call-to-action)

See `assets/notification-example.php` for a complete example.

## Error Handling

The channel automatically logs errors to Laravel's log system. Failed requests will throw exceptions that can be caught by your application's exception handler.

## Common Issues

- **Template Not Found**: Ensure your template name matches exactly what's approved in WhatsApp Business Manager
- **Invalid Phone Number**: Phone numbers must be in international format (e.g., +1234567890)
- **API Errors**: Check your access token and phone number ID are correct
- **Component Mismatch**: Ensure the number of components matches your template definition

## References

- [Configuration Guide](references/CONFIGURATION.md) - Detailed configuration instructions
- [Usage Examples](references/USAGE.md) - More usage examples and patterns
- [API Reference](references/API.md) - Complete API documentation
