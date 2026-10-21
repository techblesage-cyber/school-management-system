<?php

namespace App\Filament\School\Resources\SmsMessages;

use App\Enums\SmsStatus;
use App\Filament\School\Resources\SmsMessages\Pages\ListSmsMessages;
use App\Models\SmsMessage;
use App\Services\Sms\Sms;
use App\Support\PhoneNumber;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Every text message sent to parents, with whether it went through.
 */
class SmsMessageResource extends Resource
{
    protected static ?string $model = SmsMessage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'Communication';

    protected static ?string $navigationLabel = 'SMS sent';

    protected static ?string $modelLabel = 'SMS';

    protected static ?string $pluralModelLabel = 'SMS messages';

    protected static ?string $slug = 'sms';

    protected static ?int $navigationSort = 3;

    public static function shouldRegisterNavigation(): bool
    {
        return Sms::enabledFor(Filament::getTenant()) && parent::shouldRegisterNavigation();
    }

    public static function table(Table $table): Table
    {
        return self::smsTable($table);
    }

    public static function smsTable(Table $table, bool $withSchool = false): Table
    {
        return $table
            ->columns(array_values(array_filter([
                $withSchool ? TextColumn::make('school.name')->label('School')->searchable() : null,
                TextColumn::make('to')
                    ->formatStateUsing(fn (string $state): ?string => PhoneNumber::display($state))
                    ->searchable(),
                TextColumn::make('message')
                    ->limit(80)
                    ->tooltip(fn (SmsMessage $record): string => $record->message)
                    ->wrap()
                    ->visibleFrom('md'),
                TextColumn::make('purpose')
                    ->formatStateUsing(fn (string $state): string => str($state)->replace('_', ' ')->ucfirst())
                    ->badge()
                    ->color('gray')
                    ->visibleFrom('lg'),
                TextColumn::make('status')
                    ->badge()
                    ->description(fn (SmsMessage $record): ?string => $record->error ? str($record->error)->limit(60) : null),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('j M Y, H:i')
                    ->sortable(),
            ])))
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(SmsStatus::class),
                SelectFilter::make('purpose')->options([
                    'payment_receipt' => 'Payment receipt',
                    'fee_reminder' => 'Fee reminder',
                    'circular' => 'Circular',
                    'report_card' => 'Report card',
                    'portal_login' => 'Portal login',
                ]),
            ])
            ->emptyStateHeading('No SMS sent yet');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSmsMessages::route('/'),
        ];
    }
}
