# API Reference

Complete API reference for WhatsApp template notification channel.

## WhatsAppChannel Class

### Methods

#### `send($notifiable, Notification $notification)`

Sends a WhatsApp template message via the WhatsApp Cloud API.

**Parameters:**
- `$notifiable` (mixed): The entity receiving the notification (typically a User model)
- `$notification` (Notification): The notification instance

**Returns:** `void`

**Throws:**
- `InvalidArgumentException`: If required message fields are missing
- `Exception`: If API request fails
- `ClientException`: If HTTP client error occurs

**Example:**
```php
$channel = new WhatsAppChannel();
$channel->send($user, new OrderConfirmationNotification($order));
```

## Notification Interface

### Required Method: `toWhatsApp($notifiable)`

Every notification class that uses the WhatsApp channel must implement this method.

**Parameters:**
- `$notifiable` (mixed): The entity receiving the notification

**Returns:** `array` with the following structure:

```php
[
    'to' => string,              // Required: Phone number in international format
    'template_name' => string,   // Required: Approved template name
    'language' => string,         // Required: Language code (e.g., 'en', 'es')
    'components' => Component,    // Required: Component object
]
```

**Example:**
```php
public function toWhatsApp($notifiable)
{
    return [
        'to' => $notifiable->mobile_no,
        'template_name' => 'order_confirmation',
        'language' => 'en',
        'components' => new Component([], [], []),
    ];
}
```

## Component Class

The `Component` class is from the `netflie/whatsapp-cloud-api` package.

### Constructor

```php
new Component(
    array $header = [],    // Header components
    array $body = [],      // Body components
    array $buttons = []    // Button components
)
```

### Header Components

Header components are optional and can contain:

#### Text Header
```php
[
    'type' => 'text',
    'text' => 'Header text',
]
```

#### Image Header
```php
[
    'type' => 'image',
    'image' => [
        'link' => 'https://example.com/image.jpg',
    ],
]
```

#### Video Header
```php
[
    'type' => 'video',
    'video' => [
        'link' => 'https://example.com/video.mp4',
    ],
]
```

#### Document Header
```php
[
    'type' => 'document',
    'document' => [
        'link' => 'https://example.com/document.pdf',
        'filename' => 'document.pdf',
    ],
]
```

### Body Components

Body components contain dynamic text parameters for your template:

```php
[
    [
        'type' => 'text',
        'text' => 'First parameter value',
    ],
    [
        'type' => 'text',
        'text' => 'Second parameter value',
    ],
]
```

**Important:** The number and order of body components must match your template definition in WhatsApp Business Manager.

### Button Components

#### URL Button
```php
[
    'type' => 'url',
    'sub_type' => 'url',
    'index' => 0,  // Button index (0, 1, or 2)
    'parameters' => [
        [
            'type' => 'text',
            'text' => 'https://example.com',
        ],
    ],
]
```

#### Quick Reply Button
```php
[
    'type' => 'button',
    'sub_type' => 'quick_reply',
    'index' => 0,
    'parameters' => [
        [
            'type' => 'payload',
            'payload' => 'BUTTON_PAYLOAD',
        ],
    ],
]
```

#### Call-to-Action Button
```php
[
    'type' => 'button',
    'sub_type' => 'cta_url',
    'index' => 0,
    'parameters' => [
        [
            'type' => 'text',
            'text' => 'https://example.com',
        ],
    ],
]
```

## Configuration

### Config Keys

Access configuration via `config('services.whatsapp.*')`:

- `config('services.whatsapp.phone_number_id')`: Phone Number ID
- `config('services.whatsapp.access_token')`: Access Token
- `config('services.whatsapp.business_account_id')`: Business Account ID (optional)

### Environment Variables

- `WHATSAPP_PHONE_NUMBER_ID`: Phone Number ID
- `WHATSAPP_ACCESS_TOKEN`: Access Token
- `WHATSAPP_BUSINESS_ACCOUNT_ID`: Business Account ID (optional)

## Error Handling

### Exception Types

1. **InvalidArgumentException**: Thrown when required message fields are missing
2. **Exception**: Thrown when API returns non-200 status code
3. **ClientException**: Thrown when HTTP client error occurs

### Error Logging

All errors are automatically logged to Laravel's log system with context:

```php
Log::error('WhatsApp API error', [
    'status_code' => $statusCode,
    'response' => $responseBody,
    'to' => $phoneNumber,
    'template' => $templateName,
]);
```

## Response Handling

The channel checks the HTTP status code:

- **200**: Success - Message sent
- **Other**: Error - Exception thrown with response body

## Phone Number Format

Phone numbers must be in international format:

- ✅ Correct: `+1234567890`, `+8613800138000`
- ❌ Incorrect: `1234567890`, `(123) 456-7890`, `123-456-7890`

## Template Requirements

1. **Template Name**: Must match exactly with approved template in WhatsApp Business Manager
2. **Language Code**: Must match template language (e.g., 'en', 'es', 'zh')
3. **Components**: Must match template structure (number and type of parameters)

## Rate Limits

WhatsApp Cloud API has rate limits:
- **Tier 1**: 1,000 conversations per day
- **Tier 2**: 10,000 conversations per day
- **Tier 3**: 100,000 conversations per day

Check your tier in Meta Business Suite. Exceeding limits will result in errors.

## Best Practices

1. **Validate phone numbers** before sending
2. **Handle exceptions** in your application code
3. **Queue notifications** for better performance
4. **Monitor API usage** to avoid rate limits
5. **Test templates** before deploying
6. **Use appropriate language codes** for your audience
7. **Keep component structure** matching template definition

## Related Documentation

- [WhatsApp Cloud API Documentation](https://developers.facebook.com/docs/whatsapp/cloud-api)
- [Template Message Guide](https://developers.facebook.com/docs/whatsapp/cloud-api/guides/send-message-templates)
- [Component Reference](https://developers.facebook.com/docs/whatsapp/cloud-api/reference/messages#template-object)
