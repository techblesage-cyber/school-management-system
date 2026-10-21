<?php

namespace App\Filament\School\Resources\Payments\Pages;

use App\Filament\School\Resources\Payments\PaymentResource;
use App\Models\Payment;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;

    protected static bool $canCreateAnother = true;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        /** @var Payment $payment */
        $payment = $this->getRecord();

        return self::receiptNotification($payment);
    }

    /**
     * "Payment recorded, receipt RCT-2026-0007", with a button to print it.
     */
    public static function receiptNotification(Payment $payment): Notification
    {
        return Notification::make()
            ->success()
            ->title(Money::format($payment->amount).' received from '.$payment->student->full_name)
            ->body("Receipt {$payment->receipt_number}. Balance now ".Money::balance($payment->student->feeBalance()).'.')
            ->persistent()
            ->actions([
                Action::make('print')
                    ->label('Print receipt')
                    ->button()
                    ->url(route('fees.receipt', $payment, absolute: false), shouldOpenInNewTab: true),
            ]);
    }
}
