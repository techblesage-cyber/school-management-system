<?php

namespace App\Filament\School\Resources\FeeItems;

use App\Enums\Residence;
use App\Filament\School\Fields\FeeFields;
use App\Filament\School\Resources\FeeItems\Pages\ManageFeeItems;
use App\Models\AcademicTerm;
use App\Models\FeeItem;
use App\Models\SchoolClass;
use App\Support\Money;
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
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The fee structure: what each class pays per term. Billing uses it to
 * fill each student's invoice.
 */
class FeeItemResource extends Resource
{
    protected static ?string $model = FeeItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|UnitEnum|null $navigationGroup = 'Fees';

    protected static ?string $navigationLabel = 'Fee structure';

    protected static ?string $modelLabel = 'fee item';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'fee-structure';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                FeeFields::term(),
                TextInput::make('name')
                    ->label('Item')
                    ->placeholder('e.g. Tuition, Boarding, Development fee')
                    ->required()
                    ->maxLength(100),
                FeeFields::amount()
                    ->minValue(0),
                Select::make('residence')
                    ->label('Who pays it')
                    ->options(Residence::class)
                    ->placeholder('Day scholars and boarders'),
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
                    ->helperText('Leave empty if every class pays this item.')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['classes', 'academicTerm']))
            ->columns([
                TextColumn::make('name')
                    ->label('Item')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('amount')
                    ->formatStateUsing(fn ($state): string => Money::format($state))
                    ->sortable()
                    ->summarize(Sum::make()->label('Total')->formatStateUsing(fn ($state): string => Money::format($state))),
                TextColumn::make('applies_to')
                    ->label('Classes')
                    ->state(fn (FeeItem $record): array => $record->classes->sortBy('sort_order')->pluck('name')->all())
                    ->badge()
                    ->color('gray')
                    ->placeholder('All classes'),
                TextColumn::make('residence')
                    ->label('Who pays')
                    ->placeholder('Everyone'),
                TextColumn::make('academicTerm.year')
                    ->label('Term')
                    ->formatStateUsing(fn (FeeItem $record): ?string => $record->academicTerm?->name),
            ])
            ->defaultSort('name')
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
                        ? $query->where(fn (Builder $query) => $query
                            ->whereDoesntHave('classes')
                            ->orWhereHas('classes', fn (Builder $query) => $query->whereKey($data['value'])))
                        : $query),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No fees set for this term')
            ->emptyStateDescription('Add each fee item, or copy last term\'s fees with "Copy from another term".');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageFeeItems::route('/'),
        ];
    }
}
