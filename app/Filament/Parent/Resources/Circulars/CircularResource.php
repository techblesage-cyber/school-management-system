<?php

namespace App\Filament\Parent\Resources\Circulars;

use App\Filament\Parent\Resources\Circulars\Pages\ListCirculars;
use App\Filament\Parent\Resources\Circulars\Pages\ViewCircular;
use App\Models\Circular;
use App\Models\ParentAccount;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

/**
 * Circulars from the schools of the parent's children.
 */
class CircularResource extends Resource
{
    protected static ?string $model = Circular::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $slug = 'circulars';

    protected static ?int $navigationSort = 2;

    // Access is limited to circulars sent to this parent by the query below
    protected static bool $shouldSkipAuthorization = true;

    public static function getEloquentQuery(): Builder
    {
        $parent = Filament::auth()->user();

        return $parent instanceof ParentAccount
            ? Circular::query()->withoutGlobalScopes()->with('school')->forParentsOf($parent->children())->orderByDesc('published_at')
            : Circular::query()->withoutGlobalScopes()->whereRaw('1 = 0');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->weight('bold')
                    ->description(fn (Circular $record): string => $record->excerpt(100))
                    ->searchable()
                    ->wrap(),
                TextColumn::make('school.name')
                    ->label('From')
                    ->visibleFrom('md'),
                TextColumn::make('published_at')
                    ->label('Date')
                    ->date('j M Y')
                    ->sortable(),
            ])
            ->recordUrl(fn (Circular $record): string => static::getUrl('view', ['record' => $record]))
            ->defaultSort('published_at', 'desc')
            ->emptyStateHeading('No circulars yet');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('meta')
                            ->hiddenLabel()
                            ->state(fn (Circular $record): string => $record->school->name.' · '.$record->published_at->format('j M Y'))
                            ->color('gray'),
                        TextEntry::make('body')
                            ->hiddenLabel()
                            ->state(fn (Circular $record): HtmlString => new HtmlString($record->safeBody()))
                            ->prose(),
                        TextEntry::make('attachment_name')
                            ->label('Attachment')
                            ->icon(Heroicon::OutlinedPaperClip)
                            ->url(fn (Circular $record): ?string => $record->attachment ? route('circulars.attachment', $record, absolute: false) : null)
                            ->openUrlInNewTab()
                            ->visible(fn (Circular $record): bool => filled($record->attachment)),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCirculars::route('/'),
            'view' => ViewCircular::route('/{record}'),
        ];
    }
}
