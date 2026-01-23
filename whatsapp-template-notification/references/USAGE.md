# Usage Guide

Complete guide for using WhatsApp template notifications in your Laravel application.

## Basic Usage

### Creating a Notification Class

Create a notification class that implements the `toWhatsApp()` method:

```php
<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Netflie\WhatsAppCloudApi\Message\Template\Component;

class OrderConfirmationNotification extends Notification
{
    public function __construct(public $order)
    {
        //
    }

    public function via($notifiable)
    {
        return ['whatsapp'];
    }

    public function toWhatsApp($notifiable)
    {
        return [
            'to' => $notifiable->mobile_no,
            'template_name' => 'order_confirmation',
            'language' => 'en',
            'components' => new Component(
                [],
                [
                    [
                        'type' => 'text',
                        'text' => $this->order->order_number,
                    ],
                    [
                        'type' => 'text',
                        'text' => $this->order->total_amount,
                    ],
                ],
                []
            )
        ];
    }
}
```

### Sending to a Single User

```php
use App\Notifications\OrderConfirmationNotification;

$user->notify(new OrderConfirmationNotification($order));
```

### Sending to Multiple Users

```php
use Illuminate\Support\Facades\Notification;
use App\Notifications\OrderConfirmationNotification;

Notification::send($users, new OrderConfirmationNotification($order));
```

## Advanced Patterns

### Queued Notifications

Make notifications queued for better performance:

```php
use Illuminate\Contracts\Queue\ShouldQueue;

class OrderConfirmationNotification extends Notification implements ShouldQueue
{
    use Queueable;
    
    // ... rest of the class
}
```

### Conditional Sending

Only send if user has WhatsApp number:

```php
public function via($notifiable)
{
    if ($notifiable->mobile_no && $notifiable->wants_whatsapp_notifications) {
        return ['whatsapp'];
    }
    
    return [];
}
```

### Multiple Channels

Send via WhatsApp and email:

```php
public function via($notifiable)
{
    return ['whatsapp', 'mail'];
}
```

### Custom Phone Number Field

If your model uses a different field name:

```php
public function toWhatsApp($notifiable)
{
    return [
        'to' => $notifiable->phone, // or $notifiable->whatsapp_number
        // ... rest
    ];
}
```

## Template Components

### Text Components

```php
'components' => new Component(
    [],
    [
        [
            'type' => 'text',
            'text' => 'Dynamic text value',
        ],
    ],
    []
)
```

### Header Components

#### Text Header

```php
'components' => new Component(
    [
        [
            'type' => 'text',
            'text' => 'Header Text',
        ],
    ],
    [/* body */],
    []
)
```

#### Image Header

```php
'components' => new Component(
    [
        [
            'type' => 'image',
            'image' => [
                'link' => 'https://example.com/image.jpg',
            ],
        ],
    ],
    [/* body */],
    []
)
```

### Button Components

#### URL Button

```php
'components' => new Component(
    [],
    [/* body */],
    [
        [
            'type' => 'url',
            'sub_type' => 'url',
            'index' => 0,
            'parameters' => [
                [
                    'type' => 'text',
                    'text' => 'https://example.com/order/123',
                ],
            ],
        ],
    ]
)
```

#### Quick Reply Button

```php
'components' => new Component(
    [],
    [/* body */],
    [
        [
            'type' => 'button',
            'sub_type' => 'quick_reply',
            'index' => 0,
            'parameters' => [
                [
                    'type' => 'payload',
                    'payload' => 'CONFIRM_ORDER_123',
                ],
            ],
        ],
    ]
)
```

## Real-World Examples

### Order Confirmation

```php
class OrderConfirmationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public $order)
    {
        //
    }

    public function via($notifiable)
    {
        return ['whatsapp'];
    }

    public function toWhatsApp($notifiable)
    {
        return [
            'to' => $notifiable->mobile_no,
            'template_name' => 'order_confirmation',
            'language' => 'en',
            'components' => new Component(
                [],
                [
                    ['type' => 'text', 'text' => $this->order->order_number],
                    ['type' => 'text', 'text' => $this->order->formatted_total],
                    ['type' => 'text', 'text' => $this->order->estimated_delivery],
                ],
                [
                    [
                        'type' => 'url',
                        'sub_type' => 'url',
                        'index' => 0,
                        'parameters' => [
                            ['type' => 'text', 'text' => route('orders.show', $this->order->id)],
                        ],
                    ],
                ]
            )
        ];
    }
}
```

### Password Reset

```php
class PasswordResetNotification extends Notification
{
    public function __construct(public $token)
    {
        //
    }

    public function via($notifiable)
    {
        return ['whatsapp'];
    }

    public function toWhatsApp($notifiable)
    {
        $resetUrl = route('password.reset', ['token' => $this->token, 'email' => $notifiable->email]);
        
        return [
            'to' => $notifiable->mobile_no,
            'template_name' => 'password_reset',
            'language' => 'en',
            'components' => new Component(
                [],
                [
                    ['type' => 'text', 'text' => $notifiable->name],
                    ['type' => 'text', 'text' => $resetUrl],
                ],
                []
            )
        ];
    }
}
```

### Appointment Reminder

```php
class AppointmentReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public $appointment)
    {
        //
    }

    public function via($notifiable)
    {
        return ['whatsapp'];
    }

    public function toWhatsApp($notifiable)
    {
        return [
            'to' => $notifiable->mobile_no,
            'template_name' => 'appointment_reminder',
            'language' => 'en',
            'components' => new Component(
                [],
                [
                    ['type' => 'text', 'text' => $this->appointment->service_name],
                    ['type' => 'text', 'text' => $this->appointment->formatted_date],
                    ['type' => 'text', 'text' => $this->appointment->formatted_time],
                ],
                []
            )
        ];
    }
}
```

## Error Handling

### Try-Catch in Notification

```php
public function toWhatsApp($notifiable)
{
    try {
        return [
            'to' => $notifiable->mobile_no,
            // ... rest
        ];
    } catch (\Exception $e) {
        \Log::error('WhatsApp notification error', [
            'user' => $notifiable->id,
            'error' => $e->getMessage(),
        ]);
        throw $e;
    }
}
```

### Handling Failed Notifications

Create a failed notification handler:

```php
// In AppServiceProvider or EventServiceProvider
use Illuminate\Notifications\Events\NotificationFailed;

Event::listen(NotificationFailed::class, function ($event) {
    if ($event->channel === 'whatsapp') {
        \Log::error('WhatsApp notification failed', [
            'notifiable' => $event->notifiable->id,
            'notification' => get_class($event->notification),
            'exception' => $event->exception->getMessage(),
        ]);
    }
});
```

## Testing

### Unit Test Example

```php
use Tests\TestCase;
use App\Models\User;
use App\Notifications\OrderConfirmationNotification;
use Illuminate\Support\Facades\Notification;

class WhatsAppNotificationTest extends TestCase
{
    public function test_order_confirmation_notification()
    {
        Notification::fake();
        
        $user = User::factory()->create(['mobile_no' => '+1234567890']);
        $order = Order::factory()->create();
        
        $user->notify(new OrderConfirmationNotification($order));
        
        Notification::assertSentTo(
            $user,
            OrderConfirmationNotification::class
        );
    }
}
```

## Best Practices

1. **Queue notifications** for better performance
2. **Validate phone numbers** before sending
3. **Handle errors gracefully** with try-catch
4. **Log failed notifications** for debugging
5. **Use template variables** instead of hardcoding text
6. **Test templates** in WhatsApp Business Manager first
7. **Monitor API usage** to avoid rate limits
8. **Respect user preferences** for notifications
