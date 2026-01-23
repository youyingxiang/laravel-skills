<?php

/**
 * WhatsApp Notification Example
 * 
 * This is a complete example of how to create a WhatsApp notification class.
 * 
 * Instructions:
 * 1. Copy this file to app/Notifications/
 * 2. Update the namespace to match your application
 * 3. Customize the toWhatsApp() method for your use case
 * 4. Update the template_name to match your approved WhatsApp template
 */

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Netflie\WhatsAppCloudApi\Message\Template\Component;

class ExampleWhatsAppNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @param  mixed  $data  Your notification data
     * @return void
     */
    public function __construct(public $data)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['whatsapp'];
    }

    /**
     * Get the WhatsApp representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toWhatsApp($notifiable)
    {
        /**
         * Return structure:
         * - 'to': Phone number in international format (e.g., +1234567890)
         * - 'template_name': Name of your approved WhatsApp template
         * - 'language': Language code (e.g., 'en', 'es', 'zh')
         * - 'components': Component object with header, body, and button components
         */

        return [
            'to' => $notifiable->mobile_no, // or $notifiable->phone, depending on your model
            'template_name' => 'your_template_name', // Replace with your actual template name
            'language' => 'en', // Replace with your template's language code
            
            /**
             * Component Structure:
             * 
             * Component(
             *   $header = [],      // Header components (images, videos, documents, or text)
             *   $body = [],        // Body components (text parameters)
             *   $buttons = []      // Button components (quick reply, URL, call-to-action)
             * )
             */
            'components' => new Component(
                // Header components (optional)
                // Example with text header:
                // [
                //     [
                //         'type' => 'text',
                //         'text' => 'Header Text',
                //     ],
                // ],
                [],
                
                // Body components (required if template has body parameters)
                [
                    [
                        'type' => 'text',
                        'text' => $this->data['message'] ?? 'Default message',
                    ],
                    [
                        'type' => 'text',
                        'text' => $this->data['url'] ?? 'https://example.com',
                    ],
                ],
                
                // Button components (optional)
                // Example with URL button:
                // [
                //     [
                //         'type' => 'url',
                //         'sub_type' => 'url',
                //         'index' => 0,
                //         'parameters' => [
                //             [
                //                 'type' => 'text',
                //                 'text' => 'https://example.com',
                //             ],
                //         ],
                //     ],
                // ],
                []
            )
        ];
    }
}

/**
 * Example Usage:
 * 
 * // In your controller or service:
 * use App\Notifications\ExampleWhatsAppNotification;
 * 
 * $user->notify(new ExampleWhatsAppNotification([
 *     'message' => 'Your order has been confirmed',
 *     'url' => route('orders.show', $order->id),
 * ]));
 * 
 * // Or send to multiple users:
 * use Illuminate\Support\Facades\Notification;
 * 
 * Notification::send($users, new ExampleWhatsAppNotification([
 *     'message' => 'New update available',
 *     'url' => route('updates.index'),
 * ]));
 */
