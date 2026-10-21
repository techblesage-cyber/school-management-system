<?php

namespace App\Filament\School\Resources\Invoices\Pages;

use App\Filament\School\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    /**
     * No lines entered: fill the invoice from the fee structure.
     */
    protected function afterCreate(): void
    {
        /** @var Invoice $invoice */
        $invoice = $this->getRecord();

        if (! $invoice->items()->exists()) {
            $invoice->addFeeLines();
        }

        $invoice->recalculateTotal();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
