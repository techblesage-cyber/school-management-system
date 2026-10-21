<?php

namespace App\Filament\School\Resources\Payments\Pages;

use App\Filament\School\Resources\Payments\Actions\PaymentActions;
use App\Filament\School\Resources\Payments\PaymentResource;
use App\Models\Payment;
use Filament\Resources\Pages\ViewRecord;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    public function getSubheading(): ?string
    {
        /** @var Payment $payment */
        $payment = $this->getRecord();

        return $payment->isVoided()
            ? "VOID: {$payment->void_reason} ({$payment->voided_at->format('j M Y')}, {$payment->voidedBy?->name})"
            : "Received by {$payment->receivedBy?->name}";
    }

    protected function getHeaderActions(): array
    {
        return [
            PaymentActions::printReceipt(),
            PaymentActions::void(),
        ];
    }
}
