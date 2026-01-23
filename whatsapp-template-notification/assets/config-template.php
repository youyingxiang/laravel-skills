<?php

/**
 * WhatsApp Configuration Template
 * 
 * Add this configuration to your config/services.php file
 * 
 * Instructions:
 * 1. Open config/services.php
 * 2. Add the 'whatsapp' array to the return array
 * 3. Set environment variables in your .env file
 */

return [
    // ... other service configurations ...

    'whatsapp' => [
        /**
         * WhatsApp Phone Number ID
         * 
         * This is the phone number ID from your WhatsApp Business Account.
         * You can find this in the WhatsApp Business Manager or Meta Business Suite.
         * 
         * Environment variable: WHATSAPP_PHONE_NUMBER_ID
         */
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),

        /**
         * WhatsApp Access Token
         * 
         * This is the access token for your WhatsApp Business API.
         * Generate this in the Meta Business Suite under WhatsApp > API Setup.
         * 
         * Environment variable: WHATSAPP_ACCESS_TOKEN
         * 
         * Security Note: Keep this token secure and never commit it to version control.
         */
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),

        /**
         * WhatsApp Business Account ID (Optional)
         * 
         * This is your WhatsApp Business Account ID.
         * Not required for basic functionality, but may be needed for advanced features.
         * 
         * Environment variable: WHATSAPP_BUSINESS_ACCOUNT_ID
         */
        'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
    ],
];
