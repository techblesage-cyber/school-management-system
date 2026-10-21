<?php

namespace App\Filament\Parent\Widgets;

use App\Filament\Parent\Resources\Circulars\CircularResource;
use App\Models\Circular;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * The latest circulars from the children's schools.
 */
class SchoolCirculars extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Latest circulars';

    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => CircularResource::getEloquentQuery()->limit(5))
            ->columns([
                TextColumn::make('title')
                    ->weight('bold')
                    ->description(fn (Circular $record): string => $record->excerpt(100))
                    ->wrap(),
                TextColumn::make('school.name')
                    ->label('From')
                    ->visibleFrom('md'),
                TextColumn::make('published_at')
                    ->label('Date')
                    ->date('j M Y'),
            ])
            ->recordUrl(fn (Circular $record): string => CircularResource::getUrl('view', ['record' => $record]))
            ->defaultSort('published_at', 'desc')
            ->paginated(false)
            ->emptyStateHeading('No circulars yet');
    }
}
