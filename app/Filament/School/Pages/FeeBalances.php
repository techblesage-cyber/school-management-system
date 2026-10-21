<?php

namespace App\Filament\School\Pages;

use App\Enums\Residence;
use App\Enums\SchoolAbility;
use App\Enums\StudentStatus;
use App\Filament\School\Fields\FeeFields;
use App\Filament\School\Resources\Payments\Pages\CreatePayment;
use App\Models\Payment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Support\Money;
use BackedEnum;
use App\Services\Sms\Sms;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * Every student's fees position: billed, paid and balance. The bursar's
 * main screen: find a student, record their payment, print the receipt.
 */
class FeeBalances extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = 'Fees';

    protected static ?string $navigationLabel = 'Balances';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Fee balances';

    protected static ?string $slug = 'fee-balances';

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->canInSchool(SchoolAbility::ViewFees);
    }

    public function getSubheading(): ?string
    {
        $outstanding = (int) Student::query()
            ->withoutGlobalScopes()
            ->where('school_id', $this->school()->getKey())
            ->whereRaw(Student::feeBalanceSql().' > 0')
            ->sum(DB::raw(Student::feeBalanceSql()));

        return 'Total owed by all students: '.Money::format($outstanding);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Student::query()
                ->withoutGlobalScopes()
                ->where('students.school_id', $this->school()->getKey())
                ->withFeeBalance()
                ->with(['schoolClass', 'stream']))
            ->columns([
                TextColumn::make('admission_number')
                    ->label('Adm. no.')
                    ->searchable()
                    ->visibleFrom('md'),
                TextColumn::make('last_name')
                    ->label('Student')
                    ->formatStateUsing(fn (Student $record): string => $record->full_name)
                    ->description(fn (Student $record): ?string => $record->class_and_stream)
                    ->searchable(['first_name', 'last_name', 'other_names'])
                    ->sortable(['last_name', 'first_name']),
                TextColumn::make('residence')
                    ->toggleable()
                    ->visibleFrom('xl'),
                TextColumn::make('fees_billed')
                    ->label('Billed')
                    ->formatStateUsing(fn ($state): string => Money::format($state))
                    ->toggleable()
                    ->visibleFrom('lg'),
                TextColumn::make('fees_paid')
                    ->label('Paid')
                    ->formatStateUsing(fn ($state): string => Money::format($state))
                    ->toggleable()
                    ->visibleFrom('lg'),
                TextColumn::make('fee_balance')
                    ->label('Balance')
                    ->formatStateUsing(fn ($state): string => Money::balance($state))
                    ->color(fn ($state): string => match (true) {
                        (int) $state > 0 => 'danger',
                        (int) $state < 0 => 'info',
                        default => 'success',
                    })
                    ->weight('bold')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw(Student::feeBalanceSql().' '.($direction === 'desc' ? 'desc' : 'asc'))),
            ])
            ->defaultSort('fee_balance', 'desc')
            ->filters([
                Filter::make('owing')
                    ->label('Only students who owe')
                    ->default()
                    ->query(fn (Builder $query): Builder => $query->whereRaw(Student::feeBalanceSql().' > 0')),
                SelectFilter::make('school_class_id')
                    ->label('Class')
                    ->options(fn (): array => SchoolClass::query()
                        ->where('school_id', $this->school()->getKey())
                        ->orderBy('sort_order')
                        ->pluck('name', 'id')
                        ->all()),
                SelectFilter::make('residence')
                    ->options(Residence::class),
                SelectFilter::make('status')
                    ->options(StudentStatus::class)
                    ->default(StudentStatus::Active->value),
            ])
            ->recordActions([
                $this->recordPaymentAction(),
                $this->reminderAction(),
                Action::make('statement')
                    ->label('Statement')
                    ->tooltip('Print fee statement')
                    ->iconButton()
                    ->icon(Heroicon::OutlinedPrinter)
                    ->color('gray')
                    ->url(fn (Student $record): string => route('fees.statement', $record, absolute: false))
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([
                BulkAction::make('sendReminders')
                    ->label('Send SMS reminders')
                    ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                    ->visible(fn (): bool => $this->canSendReminders())
                    ->requiresConfirmation()
                    ->modalDescription('Texts the main parent of each selected student their fees balance. Students who don\'t owe are skipped.')
                    ->action(function (EloquentCollection $records): void {
                        abort_unless($this->canSendReminders(), 403);

                        $sent = $records->filter(fn (Student $student): bool => Sms::feeReminder($student) !== null)->count();

                        Notification::make()
                            ->title($sent > 0 ? "Sending {$sent} ".str('reminder')->plural($sent) : 'No reminders sent')
                            ->body($sent > 0 ? null : 'The selected students don\'t owe, or their parents have no phone number.')
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->emptyStateHeading('No balances to show')
            ->emptyStateDescription('Nobody owes fees with the current filters.');
    }

    protected function recordPaymentAction(): Action
    {
        return Action::make('recordPayment')
            ->label('Record payment')
            ->tooltip('Record payment')
            ->button()
            ->size('sm')
            ->labeledFrom('md')
            ->icon(Heroicon::OutlinedBanknotes)
            ->authorize(fn (): bool => Filament::auth()->user()?->can('create', Payment::class) ?? false)
            ->modalHeading(fn (Student $record): string => "Payment from {$record->full_name}")
            ->modalDescription(fn (Student $record): string => 'Current balance: '.Money::balance($record->fee_balance))
            ->schema(FeeFields::paymentFields(withStudent: false))
            ->action(function (Student $record, array $data): void {
                $payment = Payment::create([
                    ...$data,
                    'school_id' => $this->school()->getKey(),
                    'student_id' => $record->getKey(),
                ]);

                CreatePayment::receiptNotification($payment)->send();
            });
    }

    protected function reminderAction(): Action
    {
        return Action::make('reminder')
            ->label('SMS reminder')
            ->tooltip('Text the parent their balance')
            ->iconButton()
            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
            ->color('gray')
            ->visible(fn (Student $record): bool => $this->canSendReminders() && (int) $record->fee_balance > 0)
            ->requiresConfirmation()
            ->modalHeading(fn (Student $record): string => "Text {$record->full_name}'s parent?")
            ->modalDescription(fn (Student $record): string => 'They will be told the balance of '.Money::format($record->fee_balance).'.')
            ->action(function (Student $record): void {
                abort_unless($this->canSendReminders(), 403);

                $sms = Sms::feeReminder($record);

                Notification::make()
                    ->title($sms ? 'Reminder on its way' : 'No reminder sent')
                    ->body($sms ? null : 'The parent has no phone number.')
                    ->color($sms ? 'success' : 'warning')
                    ->send();
            });
    }

    protected function canSendReminders(): bool
    {
        $user = Filament::auth()->user();

        return Sms::enabledFor($this->school())
            && $user instanceof User
            && $user->canInSchool(SchoolAbility::ManageFees, $this->school());
    }

    protected function school(): School
    {
        /** @var School */
        return Filament::getTenant();
    }
}
