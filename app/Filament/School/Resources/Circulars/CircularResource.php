<?php

namespace App\Filament\School\Resources\Circulars;

use App\Enums\CircularAudience;
use App\Enums\SchoolAbility;
use App\Filament\School\Resources\Circulars\Pages\CreateCircular;
use App\Filament\School\Resources\Circulars\Pages\EditCircular;
use App\Filament\School\Resources\Circulars\Pages\ListCirculars;
use App\Filament\School\Resources\Circulars\Pages\ViewCircular;
use App\Models\Circular;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\Sms\Sms;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * Circulars: letters and notices to parents and/or staff.
 */
class CircularResource extends Resource
{
    protected static ?string $model = Circular::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'Communication';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $slug = 'circulars';

    protected static ?int $navigationSort = 1;

    /**
     * Staff who don't send circulars only see published staff circulars.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        return static::canManage() ? $query : $query->published()->forStaff();
    }

    public static function canManage(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->canInSchool(SchoolAbility::ManageCirculars);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columnSpanFull()
                    ->columns(['md' => 2])
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Radio::make('audience')
                            ->label('For')
                            ->options(CircularAudience::class)
                            ->default(CircularAudience::Parents->value)
                            ->required()
                            ->live(),
                        // Tick boxes, so every class is in view without opening a list
                        CheckboxList::make('classes')
                            ->label('Only the parents of these classes')
                            ->relationship(
                                'classes',
                                'name',
                                modifyQueryUsing: fn (Builder $query): Builder => $query
                                    ->where('school_classes.school_id', Filament::getTenant()?->getKey())
                                    ->orderBy('sort_order')
                                    ->orderBy('name'),
                            )
                            ->columns(['default' => 3, 'sm' => 4, 'lg' => 6])
                            ->gridDirection('row')
                            ->bulkToggleable()
                            ->helperText(fn (): string => self::hasClasses()
                                ? 'Tick the classes this is for. Tick none to send it to the parents of every class.'
                                : 'This school has no classes yet, so it goes to the parents of every child. Add classes under Setup → Classes & streams.')
                            ->visible(fn (Get $get): bool => self::audience($get('audience'))?->includesParents() ?? true)
                            ->columnSpanFull(),
                        RichEditor::make('body')
                            ->label('Message')
                            ->toolbarButtons([
                                ['bold', 'italic', 'underline', 'link'],
                                ['h2', 'h3'],
                                ['bulletList', 'orderedList'],
                                ['undo', 'redo'],
                            ])
                            ->required()
                            ->columnSpanFull(),
                        FileUpload::make('attachment')
                            ->label('Attachment')
                            ->helperText('Optional, e.g. the signed letter as a PDF. Up to 5 MB.')
                            ->disk('local')
                            ->directory('circulars')
                            ->visibility('private')
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                            ->maxSize(5120)
                            ->storeFileNamesIn('attachment_name')
                            ->downloadable(),
                        DateTimePicker::make('published_at')
                            ->label('Publish on')
                            ->seconds(false)
                            ->helperText('Leave empty to keep it as a draft. A future date publishes it then.'),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columnSpanFull()
                    ->columns(['md' => 3])
                    ->schema([
                        TextEntry::make('audience')->label('For'),
                        TextEntry::make('classes.name')->label('Classes')->badge()->placeholder('All classes'),
                        TextEntry::make('published_at')->label('Published')->dateTime('j M Y, H:i')->placeholder('Draft'),
                        TextEntry::make('body')
                            ->hiddenLabel()
                            ->state(fn (Circular $record): HtmlString => new HtmlString($record->safeBody()))
                            ->prose()
                            ->columnSpanFull(),
                        TextEntry::make('attachment_name')
                            ->label('Attachment')
                            ->icon(Heroicon::OutlinedPaperClip)
                            ->url(fn (Circular $record): ?string => $record->attachment ? route('circulars.attachment', $record, absolute: false) : null)
                            ->openUrlInNewTab()
                            ->visible(fn (Circular $record): bool => filled($record->attachment))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('classes'))
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (Circular $record): string => $record->excerpt(90))
                    ->wrap(),
                TextColumn::make('audience')
                    ->label('For')
                    ->badge()
                    ->color('gray')
                    ->visibleFrom('md'),
                TextColumn::make('classes.name')
                    ->label('Classes')
                    ->badge()
                    ->placeholder('All')
                    ->visibleFrom('lg'),
                TextColumn::make('published_at')
                    ->label('Published')
                    ->state(fn (Circular $record): string => match (true) {
                        $record->published_at === null => 'Draft',
                        $record->published_at->isFuture() => 'From '.$record->published_at->format('j M Y, H:i'),
                        default => $record->published_at->format('j M Y'),
                    })
                    ->color(fn (Circular $record): string => $record->isPublished() ? 'success' : 'gray')
                    ->badge()
                    ->sortable(),
            ])
            ->defaultSort(fn (Builder $query): Builder => $query->orderByRaw('published_at is null desc')->orderByDesc('published_at')->orderByDesc('id'))
            ->filters([
                SelectFilter::make('audience')
                    ->label('For')
                    ->options(CircularAudience::class),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('publish')
                    ->label('Publish now')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->authorize('update')
                    ->visible(fn (Circular $record): bool => ! $record->isPublished())
                    ->requiresConfirmation()
                    ->modalDescription(fn (Circular $record): string => $record->audience->includesParents()
                        ? 'Parents will see it in the parents\' portal straight away.'
                        : 'Staff will see it on their dashboard straight away.')
                    ->action(function (Circular $record): void {
                        $record->update(['published_at' => now()]);

                        Notification::make()->title('Circular published')->success()->send();
                    }),
                self::smsAction(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No circulars yet')
            ->emptyStateDescription('Write a letter or notice to parents or staff.');
    }

    /**
     * Texts the parents it is for: the title, a short preview and a link to
     * the parents' portal.
     */
    public static function smsAction(): Action
    {
        return Action::make('sendSms')
            ->label('Send by SMS')
            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
            ->color('gray')
            ->authorize('update')
            ->visible(fn (Circular $record): bool => Sms::enabledFor(Filament::getTenant()) && $record->isPublished() && $record->audience->includesParents())
            ->requiresConfirmation()
            ->modalDescription('Texts the main parent of each student it is for, once per phone number, with the title and a link to the parents\' portal. Each SMS is charged.')
            ->action(function (Circular $record): void {
                $sent = Sms::circular($record);

                Notification::make()
                    ->title($sent > 0 ? "Sending to {$sent} ".str('parent')->plural($sent) : 'No SMS sent')
                    ->body($sent > 0 ? null : 'No parents with phone numbers for these classes.')
                    ->success()
                    ->send();
            });
    }

    protected static function hasClasses(): bool
    {
        return SchoolClass::query()->withoutGlobalScopes()->where('school_id', Filament::getTenant()?->getKey())->exists();
    }

    public static function audience(mixed $value): ?CircularAudience
    {
        return $value instanceof CircularAudience ? $value : CircularAudience::tryFrom((string) $value);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCirculars::route('/'),
            'create' => CreateCircular::route('/create'),
            'view' => ViewCircular::route('/{record}'),
            'edit' => EditCircular::route('/{record}/edit'),
        ];
    }
}
