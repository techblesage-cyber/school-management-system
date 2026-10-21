<?php

namespace App\Filament\School\Resources\Students\RelationManagers;

use App\Filament\School\Fields\FeeFields;
use App\Filament\School\Resources\Payments\Actions\PaymentActions;
use App\Filament\School\Resources\Payments\Pages\CreatePayment;
use App\Models\Payment;
use App\Models\Student;
use App\Support\Money;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * "Payments" tab on a student, where the bursar can also record a payment.
 */
class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Payments';

    /**
     * Recording payments depends on fees permissions, not on whether the
     * user may edit the student, so this tab works from the view page too.
     */
    public function isReadOnly(): bool
    {
        return ! (Filament::auth()->user()?->can('create', Payment::class) ?? false);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components(FeeFields::paymentFields(withStudent: false));
    }

    public function table(Table $table): Table
    {
        /** @var Student $student */
        $student = $this->getOwnerRecord();

        return $table
            ->recordTitleAttribute('receipt_number')
            ->heading('Payments · Balance '.Money::balance($student->feeBalance()))
            ->columns([
                TextColumn::make('receipt_number')
                    ->label('Receipt')
                    ->badge(fn (Payment $record): bool => $record->isVoided())
                    ->color(fn (Payment $record): ?string => $record->isVoided() ? 'danger' : null)
                    ->description(fn (Payment $record): ?string => $record->isVoided() ? 'VOID: '.$record->void_reason : null),
                TextColumn::make('amount')
                    ->formatStateUsing(fn ($state): string => Money::format($state)),
                TextColumn::make('method')
                    ->label('Paid by')
                    ->description(fn (Payment $record): ?string => $record->reference),
                TextColumn::make('paid_on')
                    ->label('Date')
                    ->date(),
            ])
            ->defaultSort('paid_on', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label('Record payment')
                    ->mutateDataUsing(fn (array $data): array => [
                        ...$data,
                        'school_id' => $student->school_id,
                    ])
                    ->successNotification(null)
                    ->after(fn (Payment $record) => CreatePayment::receiptNotification($record)->send()),
            ])
            ->recordActions([
                PaymentActions::printReceipt(),
                PaymentActions::void(),
            ])
            ->emptyStateHeading('No payments yet');
    }
}
