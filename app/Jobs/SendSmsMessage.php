<?php

namespace App\Jobs;

use App\Enums\SmsStatus;
use App\Models\SmsMessage;
use App\Services\Sms\SmsDriver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends one queued SMS. Runs in the background (the scheduler starts the
 * queue worker every minute on the server).
 */
class SendSmsMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public SmsMessage $sms) {}

    public function handle(SmsDriver $driver): void
    {
        if ($this->sms->status === SmsStatus::Sent) {
            return;
        }

        $result = $driver->send($this->sms->to, $this->sms->message);

        $this->sms->update([
            'status' => $result->sent ? SmsStatus::Sent : SmsStatus::Failed,
            'provider_id' => $result->providerId,
            'cost' => $result->cost,
            'error' => $result->error,
            'sent_at' => $result->sent ? now() : null,
        ]);
    }
}
