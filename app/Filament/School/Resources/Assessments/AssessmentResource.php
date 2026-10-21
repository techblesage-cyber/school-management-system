<?php

namespace App\Filament\School\Resources\Assessments;

use App\Filament\School\Fields\FeeFields;
use App\Filament\School\Resources\Assessments\Pages\ManageAssessments;
use App\Models\AcademicTerm;
use App\Models\Assessment;
use App\Models\SchoolClass;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The exams and tests of each term (Beginning of term, Mid-term, End of
 * term...) and how much each counts towards the term's result.
 */
class AssessmentResource extends Resource
{
    protected static ?string $model = Assessment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Academics';

    protected static ?string $navigationLabel = 'Exams';

    protected static ?string $modelLabel = 'exam';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'exams';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                FeeFields::term(),
                TextInput::make('name')
                    ->label('Exam')
                    ->placeholder('e.g. End of term')
                    ->required()
                    ->maxLength(100),
                TextInput::make('max_score')
                    ->label('Marked out of')
                    ->integer()
                    ->minValue(1)
                    ->maxValue(1000)
                    ->default(100)
                    ->required(),
                TextInput::make('weight')
                    ->label('Weight')
                    ->helperText('How much it counts compared with the term\'s other exams. Give every exam 100 for a simple average, or e.g. 30 and 70.')
                    ->integer()
                    ->minValue(0)
                    ->maxValue(1000)
                    ->default(100)
                    ->required(),
                Select::make('classes')
                    ->label('Classes')
                    ->relationship(
                        'classes',
                        'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query
                            ->where('school_classes.school_id', Filament::getTenant()?->getKey())
                            ->orderBy('sort_order')
                            ->orderBy('name'),
                    )
                    ->multiple()
                    ->preload()
                    ->placeholder('All classes')
                    ->helperText('Leave empty if every class sits it.'),
                TextInput::make('sort_order')
                    ->label('Order on the report card')
                    ->integer()
                    ->minValue(0)
                    ->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['classes', 'academicTerm'])->withCount('marks'))
            ->columns([
                TextColumn::make('name')
                    ->label('Exam')
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('max_score')
                    ->label('Out of'),
                TextColumn::make('weight'),
                TextColumn::make('applies_to')
                    ->label('Classes')
                    ->state(fn (Assessment $record): array => $record->classes->sortBy('sort_order')->pluck('name')->all())
                    ->badge()
                    ->color('gray')
                    ->placeholder('All classes')
                    ->visibleFrom('md'),
                TextColumn::make('marks_count')
                    ->label('Marks entered')
                    ->numeric()
                    ->visibleFrom('md'),
            ])
            ->defaultSort(fn (Builder $query): Builder => $query->orderBy('sort_order')->orderBy('id'))
            ->filters([
                SelectFilter::make('academic_term_id')
                    ->label('Term')
                    ->options(fn (): array => AcademicTerm::optionsFor(Filament::getTenant()))
                    ->default(fn (): ?int => AcademicTerm::currentFor(Filament::getTenant())?->getKey()),
                SelectFilter::make('class')
                    ->label('Class')
                    ->options(fn (): array => SchoolClass::query()
                        ->where('school_id', Filament::getTenant()?->getKey())
                        ->orderBy('sort_order')
                        ->pluck('name', 'id')
                        ->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->forClass((int) $data['value'])
                        : $query),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No exams set for this term')
            ->emptyStateDescription('Use "Add the usual exams" for Beginning, Mid and End of term, or add your own.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAssessments::route('/'),
        ];
    }
}
