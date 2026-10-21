<?php

namespace App\Filament\School\Resources\Payments\Actions;

use App\Models\Payment;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class PaymentActions
{
    public static function printReceipt(): Action
    {
        return Action::make('receipt')
            ->label('Receipt')
            ->tooltip('Print receipt')
            ->icon(Heroicon::OutlinedPrinter)
            ->color('gray')
            ->button()
            ->size('sm')
            ->labeledFrom('md')
            ->url(fn (Payment $record): string => route('fees.receipt', $record, absolute: false))
            ->openUrlInNewTab();
    }

    /**
     * Cancels a payment recorded by mistake. The receipt stays on record,
     * marked void, and no longer counts towards the student's balance.
     */
    public static function void(): Action
    {
        return Action::make('void')
            ->label('Void')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->authorize('void')
            ->visible(fn (Payment $record): bool => ! $record->isVoided())
            ->modalHeading(fn (Payment $record): string => "Void receipt {$record->receipt_number}?")
            ->modalDescription('Use this only for a payment recorded by mistake. The receipt stays on record, marked void, and no longer counts towards the balance.')
            ->modalSubmitActionLabel('Void payment')
            ->schema([
                TextInput::make('reason')
                    ->label('Reason')
                    ->placeholder('e.g. Entered twice, wrong student')
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (Payment $record, array $data): void {
                $record->void($data['reason']);

                Notification::make()->title("Receipt {$record->receipt_number} voided")->success()->send();
            });
    }
}
