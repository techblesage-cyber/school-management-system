<?php

namespace App\Filament\School\Resources\Payments;

use App\Filament\School\Resources\Payments\Pages\CreatePayment;
use App\Filament\School\Resources\Payments\Pages\ListPayments;
use App\Filament\School\Resources\Payments\Pages\ViewPayment;
use App\Filament\School\Resources\Payments\Schemas\PaymentForm;
use App\Filament\School\Resources\Payments\Tables\PaymentsTable;
use App\Models\Payment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Money received. Each payment has a printable receipt.
 */
class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Fees';

    protected static ?string $navigationLabel = 'Payments';

    protected static ?string $recordTitleAttribute = 'receipt_number';

    protected static ?string $slug = 'payments';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return PaymentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
            'create' => CreatePayment::route('/create'),
            'view' => ViewPayment::route('/{record}'),
        ];
    }
}
