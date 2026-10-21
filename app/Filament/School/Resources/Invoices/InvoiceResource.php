<?php

namespace App\Filament\School\Resources\Invoices;

use App\Filament\School\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\School\Resources\Invoices\Pages\EditInvoice;
use App\Filament\School\Resources\Invoices\Pages\ListInvoices;
use App\Filament\School\Resources\Invoices\Pages\ViewInvoice;
use App\Filament\School\Resources\Invoices\Schemas\InvoiceForm;
use App\Filament\School\Resources\Invoices\Tables\InvoicesTable;
use App\Models\Invoice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Fees invoices (bills): one per student per term.
 */
class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Fees';

    protected static ?string $navigationLabel = 'Invoices';

    protected static ?string $recordTitleAttribute = 'number';

    protected static ?string $slug = 'invoices';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return InvoiceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InvoicesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
            'create' => CreateInvoice::route('/create'),
            'view' => ViewInvoice::route('/{record}'),
            'edit' => EditInvoice::route('/{record}/edit'),
        ];
    }
}
