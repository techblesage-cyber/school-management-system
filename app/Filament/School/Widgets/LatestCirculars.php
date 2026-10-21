<?php

namespace App\Filament\School\Widgets;

use App\Filament\School\Resources\Circulars\CircularResource;
use App\Models\Circular;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * The latest circulars for staff, on the dashboard.
 */
class LatestCirculars extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Circulars for staff';

    public static function canView(): bool
    {
        return static::query()->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(static::query())
            ->columns([
                TextColumn::make('title')
                    ->weight('bold')
                    ->description(fn (Circular $record): string => $record->excerpt(100))
                    ->wrap(),
                TextColumn::make('published_at')
                    ->label('Date')
                    ->date('j M Y'),
            ])
            ->recordUrl(fn (Circular $record): string => CircularResource::getUrl('view', ['record' => $record]))
            ->defaultSort('published_at', 'desc')
            ->paginated(false);
    }

    protected static function query(): Builder
    {
        return Circular::query()->withoutGlobalScopes()
            ->where('school_id', Filament::getTenant()?->getKey())
            ->published()
            ->forStaff()
            ->where('published_at', '>=', now()->subDays(60))
            ->limit(5);
    }
}
