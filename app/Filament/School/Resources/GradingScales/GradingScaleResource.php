<?php

namespace App\Filament\School\Resources\GradingScales;

use App\Enums\ResultSummary;
use App\Filament\School\Resources\GradingScales\Pages\CreateGradingScale;
use App\Filament\School\Resources\GradingScales\Pages\EditGradingScale;
use App\Filament\School\Resources\GradingScales\Pages\ListGradingScales;
use App\Filament\School\Resources\Students\Schemas\StudentForm;
use App\Models\GradeBand;
use App\Models\GradingScale;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * How marks become grades. Each class uses one scale (set on the class).
 */
class GradingScaleResource extends Resource
{
    protected static ?string $model = GradingScale::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Setup';

    protected static ?string $navigationLabel = 'Grading scales';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'grading-scales';

    protected static ?int $navigationSort = 16;

    public static function form(Schema $schema): Schema
    {
        $isAggregate = fn (Get $get): bool => self::summary($get('summary')) === ResultSummary::Aggregate;

        return $schema
            ->components([
                Section::make('Scale')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->placeholder('e.g. Primary (D1–F9)')
                            ->required()
                            ->maxLength(100)
                            ->scopedUnique(modifyQueryUsing: StudentForm::withinSchool(...)),
                        Radio::make('summary')
                            ->label('Overall result')
                            ->options(ResultSummary::class)
                            ->default(ResultSummary::Average->value)
                            ->required()
                            ->live(),
                        TextInput::make('aggregate_subjects')
                            ->label('Number of subjects in the aggregate')
                            ->helperText('The best subjects counted, e.g. 4 for PLE. Untick "Counts towards aggregate" on subjects that shouldn\'t count.')
                            ->integer()
                            ->minValue(1)
                            ->maxValue(20)
                            ->required($isAggregate)
                            ->visible($isAggregate),
                        Repeater::make('divisions')
                            ->label('Divisions')
                            ->helperText('An aggregate up to the number given gets that division. Anything higher is "Ungraded (U)".')
                            ->table([
                                TableColumn::make('Aggregate up to')->markAsRequired(),
                                TableColumn::make('Division')->markAsRequired(),
                            ])
                            ->schema([
                                TextInput::make('max')->integer()->minValue(1)->required(),
                                TextInput::make('label')->maxLength(50)->required(),
                            ])
                            ->defaultItems(0)
                            ->addActionLabel('Add division')
                            ->visible($isAggregate),
                    ]),
                Section::make('Grades')
                    ->description('Start each grade at its lowest percentage. A student\'s mark gets the highest grade it reaches.')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('bands')
                            ->hiddenLabel()
                            ->relationship()
                            ->table([
                                TableColumn::make('Grade')->markAsRequired(),
                                TableColumn::make('From (%)')->markAsRequired(),
                                TableColumn::make('Points'),
                                TableColumn::make('Remark'),
                            ])
                            ->schema([
                                TextInput::make('grade')->required()->maxLength(10),
                                TextInput::make('min_score')->numeric()->minValue(0)->maxValue(100)->required(),
                                TextInput::make('points')->numeric()->minValue(0)->maxValue(99),
                                TextInput::make('remark')->maxLength(100),
                            ])
                            ->defaultItems(1)
                            ->minItems(1)
                            ->addActionLabel('Add grade'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('bands')->withCount('classes'))
            ->columns([
                TextColumn::make('name')
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('summary')
                    ->label('Overall result')
                    ->visibleFrom('md'),
                TextColumn::make('grades')
                    ->state(fn (GradingScale $record): string => $record->bands
                        ->map(fn (GradeBand $band): string => $band->grade.' ≥'.self::number($band->min_score))
                        ->implode(' · '))
                    ->wrap(),
                TextColumn::make('classes_count')
                    ->label('Classes using it')
                    ->visibleFrom('md'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('Classes using this scale will show marks without grades until you choose another scale for them.'),
            ])
            ->emptyStateHeading('No grading scales yet')
            ->emptyStateDescription('Use "Add standard scales" for the usual Primary, O-level and A-level grades, then choose a scale for each class.');
    }

    public static function summary(mixed $value): ?ResultSummary
    {
        return $value instanceof ResultSummary ? $value : ResultSummary::tryFrom((string) $value);
    }

    /**
     * 80, 82.5 — without trailing zeros.
     */
    public static function number(float|int|null $value): string
    {
        return $value === null ? '' : rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGradingScales::route('/'),
            'create' => CreateGradingScale::route('/create'),
            'edit' => EditGradingScale::route('/{record}/edit'),
        ];
    }
}
