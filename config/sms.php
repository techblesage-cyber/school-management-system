<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SMS
    |--------------------------------------------------------------------------
    |
    | "log" writes messages to storage/logs instead of sending them, which is
    | the safe choice while building and testing. "africastalking" sends real
    | messages through Africa's Talking (https://africastalking.com).
    |
    | Each school must also have SMS switched on in the admin panel.
    |
    */

    'driver' => env('SMS_DRIVER', 'log'),

    'africastalking' => [
        'username' => env('AFRICASTALKING_USERNAME', 'sandbox'),
        'api_key' => env('AFRICASTALKING_API_KEY'),
        // Your registered sender ID, e.g. SHAREBILITY. Leave empty to use the default.
        'sender_id' => env('AFRICASTALKING_SENDER_ID'),
    ],

];
