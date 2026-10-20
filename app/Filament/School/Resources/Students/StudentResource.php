<?php

namespace App\Filament\School\Resources\Students;

use App\Filament\School\Resources\Students\Pages\CreateStudent;
use App\Filament\School\Resources\Students\Pages\EditStudent;
use App\Filament\School\Resources\Students\Pages\ListStudents;
use App\Filament\School\Resources\Students\RelationManagers\GuardiansRelationManager;
use App\Filament\School\Resources\Students\Schemas\StudentForm;
use App\Filament\School\Resources\Students\Tables\StudentsTable;
use App\Models\Student;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class StudentResource extends Resource
{
    protected static ?string $model = Student::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|UnitEnum|null $navigationGroup = 'Students';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return StudentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StudentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            GuardiansRelationManager::class,
        ];
    }

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record instanceof Student ? $record->full_name : null;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['first_name', 'last_name', 'other_names', 'admission_number', 'lin'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Student $record */
        return array_filter([
            'Adm. no.' => $record->admission_number,
            'Class' => $record->class_and_stream,
        ]);
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['schoolClass', 'stream']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStudents::route('/'),
            'create' => CreateStudent::route('/create'),
            'edit' => EditStudent::route('/{record}/edit'),
        ];
    }
}
