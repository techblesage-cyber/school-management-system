<?php

namespace App\Services\Sms;

final class SmsResult
{
    public function __construct(
        public readonly bool $sent,
        public readonly ?string $providerId = null,
        public readonly ?string $cost = null,
        public readonly ?string $error = null,
    ) {}
}
