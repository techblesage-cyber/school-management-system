<?php

namespace App\Filament\School\Resources\AcademicTerms;

use App\Filament\School\Resources\AcademicTerms\Pages\ManageAcademicTerms;
use App\Models\AcademicTerm;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Terms of the school year. The current term is the default for fees.
 */
class AcademicTermResource extends Resource
{
    protected static ?string $model = AcademicTerm::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Setup';

    protected static ?string $navigationLabel = 'Terms';

    protected static ?string $modelLabel = 'term';

    protected static ?string $slug = 'terms';

    protected static ?int $navigationSort = 5;

    /**
     * Only in the menu for those who set it up; others don't need it there.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return static::canCreate();
    }

    public static function getRecordTitle(?Model $record): ?string
    {
        return $record instanceof AcademicTerm ? $record->name : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('year')
                    ->integer()
                    ->minValue(2000)
                    ->maxValue(2100)
                    ->default(now()->year)
                    ->required(),
                Select::make('term')
                    ->options([1 => 'Term 1', 2 => 'Term 2', 3 => 'Term 3'])
                    ->required()
                    ->rule(fn (Get $get, ?AcademicTerm $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                        $taken = AcademicTerm::query()->withoutGlobalScopes()
                            ->where('school_id', Filament::getTenant()?->getKey())
                            ->where('year', $get('year'))
                            ->where('term', $value)
                            ->when($record, fn (Builder $query) => $query->whereKeyNot($record->getKey()))
                            ->exists();

                        if ($taken) {
                            $fail('This term already exists.');
                        }
                    }),
                DatePicker::make('starts_on')
                    ->label('Opens on'),
                DatePicker::make('ends_on')
                    ->label('Closes on')
                    ->afterOrEqual('starts_on'),
                Toggle::make('is_current')
                    ->label('This is the current term')
                    ->helperText('Used as the default when billing and recording payments.')
                    ->default(true)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Term')
                    ->state(fn (AcademicTerm $record): string => $record->name)
                    ->weight('bold'),
                TextColumn::make('starts_on')
                    ->label('Opens')
                    ->date()
                    ->placeholder('—'),
                TextColumn::make('ends_on')
                    ->label('Closes')
                    ->date()
                    ->placeholder('—'),
                IconColumn::make('is_current')
                    ->label('Current')
                    ->boolean(),
            ])
            ->defaultSort(fn (Builder $query): Builder => $query->orderByDesc('year')->orderByDesc('term'))
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No terms yet')
            ->emptyStateDescription('Add the current term to start setting fees.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAcademicTerms::route('/'),
        ];
    }
}
