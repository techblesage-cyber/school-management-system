<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/**
 * Writes messages to the log instead of sending them.
 */
class LogDriver implements SmsDriver
{
    public function send(string $to, string $message): SmsResult
    {
        Log::info("SMS to {$to}: {$message}");

        return new SmsResult(sent: true, providerId: 'log');
    }
}
