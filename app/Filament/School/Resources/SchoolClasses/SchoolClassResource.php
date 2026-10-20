<?php

namespace App\Filament\School\Resources\SchoolClasses;

use App\Filament\School\Resources\SchoolClasses\Pages\CreateSchoolClass;
use App\Filament\School\Resources\SchoolClasses\Pages\EditSchoolClass;
use App\Filament\School\Resources\SchoolClasses\Pages\ListSchoolClasses;
use App\Filament\School\Resources\SchoolClasses\Schemas\SchoolClassForm;
use App\Filament\School\Resources\SchoolClasses\Tables\SchoolClassesTable;
use App\Models\SchoolClass;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SchoolClassResource extends Resource
{
    protected static ?string $model = SchoolClass::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Setup';

    protected static ?string $navigationLabel = 'Classes & streams';

    protected static ?string $modelLabel = 'class';

    protected static ?string $pluralModelLabel = 'classes';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'classes';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return SchoolClassForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SchoolClassesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSchoolClasses::route('/'),
            'create' => CreateSchoolClass::route('/create'),
            'edit' => EditSchoolClass::route('/{record}/edit'),
        ];
    }
}
