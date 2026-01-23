<?php

/**
 * WhatsApp Notification Channel for Laravel
 * 
 * This channel sends WhatsApp template messages using the WhatsApp Cloud API.
 * 
 * Usage:
 * 1. Register this channel in your service provider
 * 2. Create notification classes that implement toWhatsApp() method
 * 3. Send notifications using Laravel's notification system
 * 
 * @see SKILL.md for complete setup instructions
 */

namespace App\Channels;

use GuzzleHttp\Exception\ClientException;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Netflie\WhatsAppCloudApi\WhatsAppCloudApi;

class WhatsAppChannel
{
    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     * @param  \Illuminate\Notifications\Notification  $notification
     * @return void
     * @throws \Exception
     */
    public function send($notifiable, Notification $notification)
    {
        // Check if notification implements toWhatsApp method
        if (! method_exists($notification, 'toWhatsApp')) {
            return;
        }

        // Get the WhatsApp message data from notification
        $message = $notification->toWhatsApp($notifiable);

        // Validate required message fields
        if (!isset($message['to']) || !isset($message['template_name']) || !isset($message['language']) || !isset($message['components'])) {
            Log::error('WhatsApp notification missing required fields', ['message' => $message]);
            throw new \InvalidArgumentException('WhatsApp message must contain: to, template_name, language, and components');
        }

        try {
            // Initialize WhatsApp Cloud API client
            $whatsappCloudApi = new WhatsAppCloudApi([
                'from_phone_number_id' => config('services.whatsapp.phone_number_id'),
                'access_token' => config('services.whatsapp.access_token'),
            ]);

            // Send template message
            $result = $whatsappCloudApi->sendTemplate(
                $message['to'],
                $message['template_name'],
                $message['language'],
                $message['components']
            );

            // Check if request was successful
            if ($result->httpStatusCode() !== 200) {
                Log::error('WhatsApp API error', [
                    'status_code' => $result->httpStatusCode(),
                    'response' => $result->body(),
                    'to' => $message['to'],
                    'template' => $message['template_name'],
                ]);
                throw new \Exception('WhatsApp API error: ' . $result->body());
            }

            // Log successful send (optional, remove in production if not needed)
            Log::debug('WhatsApp message sent successfully', [
                'to' => $message['to'],
                'template' => $message['template_name'],
            ]);

        } catch (ClientException $e) {
            // Handle HTTP client exceptions
            Log::error('WhatsApp API client error', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'to' => $message['to'] ?? 'unknown',
                'template' => $message['template_name'] ?? 'unknown',
            ]);
            throw $e;
        } catch (\Exception $e) {
            // Handle any other exceptions
            Log::error('WhatsApp notification error', [
                'message' => $e->getMessage(),
                'to' => $message['to'] ?? 'unknown',
                'template' => $message['template_name'] ?? 'unknown',
            ]);
            throw $e;
        }
    }
}
