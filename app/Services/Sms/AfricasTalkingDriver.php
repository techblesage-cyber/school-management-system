<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Sends SMS through Africa's Talking. The "sandbox" username uses their test
 * environment, where nothing is delivered or charged.
 */
class AfricasTalkingDriver implements SmsDriver
{
    public function __construct(
        private readonly string $username,
        private readonly ?string $apiKey,
        private readonly ?string $senderId = null,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            (string) config('sms.africastalking.username', 'sandbox'),
            config('sms.africastalking.api_key'),
            config('sms.africastalking.sender_id'),
        );
    }

    public function endpoint(): string
    {
        return $this->username === 'sandbox'
            ? 'https://api.sandbox.africastalking.com/version1/messaging'
            : 'https://api.africastalking.com/version1/messaging';
    }

    public function send(string $to, string $message): SmsResult
    {
        if (blank($this->apiKey)) {
            return new SmsResult(sent: false, error: 'AFRICASTALKING_API_KEY is not set.');
        }

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->withHeaders(['apiKey' => $this->apiKey])
                ->timeout(20)
                ->post($this->endpoint(), array_filter([
                    'username' => $this->username,
                    'to' => $to,
                    'message' => $message,
                    'from' => $this->senderId,
                ]));
        } catch (Throwable $exception) {
            return new SmsResult(sent: false, error: $exception->getMessage());
        }

        if (! $response->successful()) {
            return new SmsResult(sent: false, error: "HTTP {$response->status()}: ".mb_substr($response->body(), 0, 300));
        }

        $recipient = $response->json('SMSMessageData.Recipients.0');

        if (! is_array($recipient)) {
            return new SmsResult(sent: false, error: (string) ($response->json('SMSMessageData.Message') ?? 'No recipient in the response.'));
        }

        // 100 Processed, 101 Sent, 102 Queued: accepted for delivery
        $accepted = in_array((int) ($recipient['statusCode'] ?? 0), [100, 101, 102], true);

        return new SmsResult(
            sent: $accepted,
            providerId: $recipient['messageId'] ?? null,
            cost: $recipient['cost'] ?? null,
            error: $accepted ? null : (string) ($recipient['status'] ?? 'Rejected'),
        );
    }
}
