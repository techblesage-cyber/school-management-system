<?php

namespace App\Filament\School\Resources\Subjects;

use App\Filament\School\Resources\Subjects\Pages\ListSubjects;
use App\Filament\School\Resources\Subjects\Schemas\SubjectForm;
use App\Filament\School\Resources\Subjects\Tables\SubjectsTable;
use App\Models\Subject;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The subjects a school teaches. Simple screen: add and edit in pop-ups.
 */
class SubjectResource extends Resource
{
    protected static ?string $model = Subject::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Setup';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'subjects';

    protected static ?int $navigationSort = 15;

    /**
     * Only in the menu for those who set it up; others don't need it there.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return static::canCreate();
    }

    public static function form(Schema $schema): Schema
    {
        return SubjectForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SubjectsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubjects::route('/'),
        ];
    }
}
