<?php

namespace App\Filament\School\Resources\Guardians;

use App\Filament\School\Resources\Guardians\Pages\CreateGuardian;
use App\Filament\School\Resources\Guardians\Pages\EditGuardian;
use App\Filament\School\Resources\Guardians\Pages\ListGuardians;
use App\Filament\School\Resources\Guardians\RelationManagers\StudentsRelationManager;
use App\Filament\School\Resources\Guardians\Schemas\GuardianForm;
use App\Filament\School\Resources\Guardians\Tables\GuardiansTable;
use App\Models\Guardian;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class GuardianResource extends Resource
{
    protected static ?string $model = Guardian::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Students';

    protected static ?string $navigationLabel = 'Parents & guardians';

    protected static ?string $modelLabel = 'parent / guardian';

    protected static ?string $pluralModelLabel = 'parents & guardians';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'parents';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return GuardianForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GuardiansTable::configure($table);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'phone', 'email'];
    }

    public static function getRelations(): array
    {
        return [
            StudentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGuardians::route('/'),
            'create' => CreateGuardian::route('/create'),
            'edit' => EditGuardian::route('/{record}/edit'),
        ];
    }
}
