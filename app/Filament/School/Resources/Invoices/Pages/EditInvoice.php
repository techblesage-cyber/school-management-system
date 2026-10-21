<?php

namespace App\Filament\School\Resources\Invoices\Pages;

use App\Filament\School\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->modalDescription('The student will no longer be billed for this term. Payments already received stay on record.'),
        ];
    }

    protected function afterSave(): void
    {
        /** @var Invoice $invoice */
        $invoice = $this->getRecord();
        $invoice->recalculateTotal();
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
