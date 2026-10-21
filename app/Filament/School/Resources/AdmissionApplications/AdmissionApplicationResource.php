<?php

namespace App\Filament\School\Resources\AdmissionApplications;

use App\Enums\ApplicationStatus;
use App\Filament\School\Resources\AdmissionApplications\Pages\CreateAdmissionApplication;
use App\Filament\School\Resources\AdmissionApplications\Pages\EditAdmissionApplication;
use App\Filament\School\Resources\AdmissionApplications\Pages\ListAdmissionApplications;
use App\Filament\School\Resources\AdmissionApplications\Pages\ViewAdmissionApplication;
use App\Filament\School\Resources\AdmissionApplications\Schemas\AdmissionApplicationForm;
use App\Filament\School\Resources\AdmissionApplications\Tables\AdmissionApplicationsTable;
use App\Models\AdmissionApplication;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AdmissionApplicationResource extends Resource
{
    protected static ?string $model = AdmissionApplication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Students';

    protected static ?string $navigationLabel = 'Admissions';

    protected static ?string $modelLabel = 'application';

    protected static ?string $pluralModelLabel = 'admission applications';

    protected static ?string $recordTitleAttribute = 'reference';

    protected static ?string $slug = 'admissions';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return AdmissionApplicationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdmissionApplicationsTable::configure($table);
    }

    /**
     * Number of applications waiting for a decision, shown in the menu.
     */
    public static function getNavigationBadge(): ?string
    {
        $pending = static::getEloquentQuery()->where('status', ApplicationStatus::Pending)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdmissionApplications::route('/'),
            'create' => CreateAdmissionApplication::route('/create'),
            'view' => ViewAdmissionApplication::route('/{record}'),
            'edit' => EditAdmissionApplication::route('/{record}/edit'),
        ];
    }
}
