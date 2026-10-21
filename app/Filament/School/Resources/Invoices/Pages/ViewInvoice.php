<?php

namespace App\Filament\School\Resources\Invoices\Pages;

use App\Filament\School\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    public function getSubheading(): ?string
    {
        /** @var Invoice $invoice */
        $invoice = $this->getRecord();

        return "{$invoice->student->full_name} · {$invoice->academicTerm->name} · Total ".Money::format($invoice->total)
            .' · Student balance now '.Money::balance($invoice->student->feeBalance());
    }

    protected function getHeaderActions(): array
    {
        /** @var Invoice $invoice */
        $invoice = $this->getRecord();

        return [
            Action::make('statement')
                ->label('Fee statement')
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->url(route('fees.statement', $invoice->student_id, absolute: false))
                ->openUrlInNewTab(),
            EditAction::make(),
        ];
    }
}
