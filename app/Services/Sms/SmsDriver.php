<?php

namespace App\Services\Sms;

interface SmsDriver
{
    /**
     * @param  string  $to  Phone number in international form, e.g. +256772123456
     */
    public function send(string $to, string $message): SmsResult;
}
