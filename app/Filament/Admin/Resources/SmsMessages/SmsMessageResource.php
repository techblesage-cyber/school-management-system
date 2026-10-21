<?php

namespace App\Filament\Admin\Resources\SmsMessages;

use App\Filament\Admin\Resources\SmsMessages\Pages\ListSmsMessages;
use App\Filament\School\Resources\SmsMessages\SmsMessageResource as SchoolSmsResource;
use App\Models\School;
use App\Models\SmsMessage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;

/**
 * SMS sent by every school, e.g. to bill schools for their messages.
 */
class SmsMessageResource extends Resource
{
    protected static ?string $model = SmsMessage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'SMS usage';

    protected static ?string $modelLabel = 'SMS';

    protected static ?string $pluralModelLabel = 'SMS messages';

    protected static ?string $slug = 'sms';

    public static function table(Table $table): Table
    {
        return SchoolSmsResource::smsTable($table, withSchool: true)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('school'))
            ->filters([
                SelectFilter::make('school_id')
                    ->label('School')
                    ->options(fn (): array => School::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),
                Filter::make('month')
                    ->schema([
                        DatePicker::make('from')->label('Sent from')->default(now()->startOfMonth()),
                        DatePicker::make('until')->label('Sent until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, $date) => $query->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, $date) => $query->whereDate('created_at', '<=', $date))),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSmsMessages::route('/'),
        ];
    }
}
