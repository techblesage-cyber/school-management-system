<?php

namespace App\Filament\School\Widgets;

use App\Models\TeachingAssignment;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * On the dashboard of anyone who teaches: their subjects and classes here.
 */
class MyTeaching extends TableWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'My subjects and classes';

    public static function canView(): bool
    {
        return static::query()->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(static::query()->with(['subject', 'schoolClass', 'stream']))
            ->columns([
                TextColumn::make('subject.name')
                    ->label('Subject')
                    ->weight('bold'),
                TextColumn::make('class_label')
                    ->label('Class'),
            ])
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderBy('subject_id')
                ->orderBy('school_class_id')
                ->orderBy('stream_id'))
            ->paginated(false);
    }

    protected static function query(): Builder
    {
        return TeachingAssignment::query()
            ->where('school_id', Filament::getTenant()?->getKey())
            ->where('user_id', Filament::auth()->id());
    }
}
