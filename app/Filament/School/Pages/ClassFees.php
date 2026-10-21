<?php

namespace App\Filament\School\Pages;

use App\Enums\SchoolAbility;
use App\Enums\StudentStatus;
use App\Filament\School\Fields\FeeFields;
use App\Filament\School\Resources\Payments\Pages\CreatePayment;
use App\Filament\Shared\InitialsAvatars;
use App\Models\AcademicTerm;
use App\Models\FeeItem;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * The bursar's view of fees, class by class and stream by stream: a tile
 * for each stream (S.1 East, S.1 West, S.2 East...) showing how much is
 * collected, and below it that stream's students with their fees for the
 * term, what they've paid and what's left, with a Pay button on each row.
 * Balances work themselves out from each class's fee structure.
 */
class ClassFees extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Fees';

    protected static ?string $navigationLabel = 'Fees by class';

    protected static ?int $navigationSort = 0;

    protected static ?string $title = 'Fees by class';

    protected static ?string $slug = 'class-fees';

    /** Which students are shown: "c:{class id}", "s:{stream id}" or "n:{class id}" (no stream). */
    #[Url(as: 'show')]
    public ?string $group = null;

    #[Url(as: 'term')]
    public int|string|null $termId = null;

    /** @var Collection<int, object>|null */
    protected ?Collection $totals = null;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->canInSchool(SchoolAbility::ViewFees);
    }

    public function mount(): void
    {
        if (! $this->term()) {
            $this->termId = AcademicTerm::currentFor($this->school())?->getKey()
                ?? AcademicTerm::query()->withoutGlobalScopes()->where('school_id', $this->school()->getKey())->orderByDesc('year')->orderByDesc('term')->value('id');
        }

        if (! $this->groupContext()) {
            $this->group = collect($this->tiles())->flatMap(fn (array $class): array => $class['tiles'])->first()['key'] ?? null;
        }
    }

    public function getSubheading(): ?string
    {
        return 'Tap a class or stream to see each student\'s fees, what they have paid and what is left. Tap a student to record a payment.';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.class-fees')
                ->viewData(fn (): array => [
                    'tiles' => $this->tiles(),
                    'terms' => AcademicTerm::optionsFor($this->school()),
                    'unbilled' => $this->unbilledCount(),
                    'canBill' => $this->canBill(),
                    'termName' => $this->term()?->name,
                    'feeSummary' => $this->feeSummary(),
                ]),
            EmbeddedTable::make(),
        ]);
    }

    public function choose(string $key): void
    {
        $this->group = $key;
        $this->totals = null;
        $this->resetTable();
    }

    public function updatedTermId(): void
    {
        $this->totals = null;
        $this->resetTable();
    }

    /**
     * Bills the students shown who have no invoice for the term yet,
     * from the fee structure.
     */
    public function billUnbilled(): void
    {
        abort_unless($this->canBill() && ($term = $this->term()) && ($context = $this->groupContext()), 403);

        $created = Invoice::billTerm($term, [$context['class']->getKey()]);

        Notification::make()
            ->title($created > 0 ? "Billed {$created} ".str('student')->plural($created)." for {$term->name}" : 'Everyone is already billed')
            ->success()
            ->send();

        $this->totals = null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->studentsQuery())
            ->heading(fn (): ?string => ($context = $this->groupContext()) ? $context['label'] : null)
            ->description(fn (): ?string => $this->groupSummary())
            ->columns([
                ImageColumn::make('photo')
                    ->disk('public')
                    ->circular()
                    ->defaultImageUrl(fn (Student $record): string => InitialsAvatars::for($record->full_name))
                    ->visibleFrom('sm'),
                TextColumn::make('last_name')
                    ->label('Student')
                    ->formatStateUsing(fn (Student $record): string => $record->full_name)
                    ->description(fn (Student $record): string => collect([
                        $record->admission_number,
                        str_starts_with((string) $this->group, 'c:') ? $record->stream?->name : null,
                        $record->residence?->getLabel(),
                    ])->filter()->implode(' · '))
                    ->weight('bold')
                    ->wrap()
                    ->searchable(['first_name', 'last_name', 'other_names', 'admission_number'])
                    ->sortable(['last_name', 'first_name']),
                TextColumn::make('term_fees')
                    ->label(fn (): string => 'Fees '.($this->term()?->name ?? 'this term'))
                    ->formatStateUsing(fn (Student $record): string => $record->term_billed ? Money::format($record->term_fees) : 'Not billed')
                    ->color(fn (Student $record): ?string => $record->term_billed ? null : 'warning')
                    ->visibleFrom('md'),
                TextColumn::make('term_paid')
                    ->label('Paid')
                    ->formatStateUsing(fn ($state): string => Money::format((int) $state))
                    ->color('success'),
                TextColumn::make('fee_balance')
                    ->label('Balance')
                    ->formatStateUsing(fn ($state): string => Money::balance((int) $state))
                    ->description(fn (Student $record): ?string => ($before = $this->broughtForward($record)) !== 0
                        ? ($before > 0 ? 'Includes '.Money::format($before).' from before' : 'After '.Money::format(-$before).' credit')
                        : null)
                    ->color(fn ($state): string => match (true) {
                        (int) $state > 0 => 'danger',
                        (int) $state < 0 => 'info',
                        default => 'success',
                    })
                    ->weight('bold')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw(Student::feeBalanceSql().' '.($direction === 'desc' ? 'desc' : 'asc'))),
            ])
            ->defaultSort(fn (Builder $query): Builder => $query->orderBy('last_name')->orderBy('first_name'))
            // Tapping a student opens the payment form (handy on phones)
            ->recordAction('pay')
            ->filters([
                Filter::make('owing')
                    ->label('Only students who owe')
                    ->query(fn (Builder $query): Builder => $query->whereRaw(Student::feeBalanceSql().' > 0')),
            ])
            ->recordActions([
                $this->payAction(),
                Action::make('statement')
                    ->label('Statement')
                    ->tooltip('Print fee statement')
                    ->iconButton()
                    ->icon(Heroicon::OutlinedPrinter)
                    ->color('gray')
                    ->url(fn (Student $record): string => route('fees.statement', $record, absolute: false))
                    ->openUrlInNewTab(),
            ])
            ->paginated([50, 100, 'all'])
            ->defaultPaginationPageOption(100)
            ->emptyStateHeading(fn (): string => $this->groupContext() ? 'No students here yet' : 'Choose a class or stream')
            ->emptyStateDescription(fn (): ?string => $this->groupContext() ? 'Students admitted to this class appear here.' : null);
    }

    protected function payAction(): Action
    {
        return Action::make('pay')
            ->label('Pay')
            ->tooltip('Record a payment')
            ->button()
            ->size('sm')
            ->icon(Heroicon::OutlinedBanknotes)
            ->authorize(fn (): bool => Filament::auth()->user()?->can('create', Payment::class) ?? false)
            ->modalHeading(fn (Student $record): string => "Payment from {$record->full_name}")
            ->modalDescription(fn (Student $record): string => 'Balance now: '.Money::balance((int) $record->fee_balance))
            ->modalSubmitActionLabel('Save payment')
            ->fillForm(fn (): array => ['academic_term_id' => $this->termId])
            ->schema(FeeFields::paymentFields(withStudent: false))
            ->action(function (Student $record, array $data): void {
                abort_unless($this->studentsQuery()->whereKey($record->getKey())->exists(), 403);

                $payment = Payment::create([
                    ...$data,
                    'school_id' => $this->school()->getKey(),
                    'student_id' => $record->getKey(),
                ]);

                $this->totals = null;
                CreatePayment::receiptNotification($payment)->send();
            });
    }

    /**
     * Students of the chosen class or stream, with this term's fees,
     * payments and their overall balance.
     */
    protected function studentsQuery(): Builder
    {
        $context = $this->groupContext();
        $termId = $this->term()?->getKey() ?? 0;

        $query = Student::query()
            ->withoutGlobalScopes()
            ->where('students.school_id', $this->school()->getKey())
            ->where('status', StudentStatus::Active)
            ->with(['stream', 'schoolClass'])
            ->withFeeBalance()
            ->selectRaw('(select coalesce(sum(invoices.total), 0) from invoices where invoices.student_id = students.id and invoices.academic_term_id = ?) as term_fees', [$termId])
            ->selectRaw('(select count(*) from invoices where invoices.student_id = students.id and invoices.academic_term_id = ?) as term_billed', [$termId])
            ->selectRaw('(select coalesce(sum(payments.amount), 0) from payments where payments.student_id = students.id and payments.voided_at is null and payments.academic_term_id = ?) as term_paid', [$termId]);

        if (! $context) {
            return $query->whereRaw('1 = 0');
        }

        $query->where('school_class_id', $context['class']->getKey());

        return match ($context['type']) {
            's' => $query->where('stream_id', $context['stream_id']),
            'n' => $query->whereNull('stream_id'),
            default => $query,
        };
    }

    /**
     * What was owed (or in credit) before this term.
     */
    protected function broughtForward(Student $student): int
    {
        return (int) $student->fee_balance - (int) $student->term_fees + (int) $student->term_paid;
    }

    /**
     * The tiles: for each class, one per stream (and "No stream" if some
     * students have none), each with its totals for the term.
     *
     * @return array<int, array{name: string, key: string, tiles: array<int, array<string, mixed>>}>
     */
    public function tiles(): array
    {
        $totals = $this->totals();

        return SchoolClass::query()
            ->withoutGlobalScopes()
            ->where('school_id', $this->school()->getKey())
            ->with('streams')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (SchoolClass $class) use ($totals): array {
                $rows = $totals->where('school_class_id', $class->getKey());
                $tiles = $class->streams->sortBy('name')
                    ->map(fn ($stream): array => $this->tile("s:{$stream->getKey()}", $stream->name, $rows->where('stream_id', $stream->getKey())))
                    ->values();

                if ($rows->whereNull('stream_id')->sum('students') > 0) {
                    $tiles->push($this->tile("n:{$class->getKey()}", $class->streams->isEmpty() ? 'All students' : 'No stream', $rows->whereNull('stream_id')));
                }

                if ($class->streams->isNotEmpty()) {
                    $tiles->prepend($this->tile("c:{$class->getKey()}", 'Whole class', $rows));
                }

                return ['name' => $class->name, 'key' => "c:{$class->getKey()}", 'tiles' => $tiles->all()];
            })
            ->filter(fn (array $class): bool => $class['tiles'] !== [])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array<string, mixed>
     */
    protected function tile(string $key, string $name, Collection $rows): array
    {
        $billed = (int) $rows->sum('billed');
        $paid = (int) $rows->sum('paid');

        return [
            'key' => $key,
            'name' => $name,
            'students' => (int) $rows->sum('students'),
            'billed' => $billed,
            'paid' => $paid,
            'owed' => (int) $rows->sum('owed'),
            'percent' => $billed > 0 ? (int) min(100, round($paid / $billed * 100)) : null,
            'active' => $this->group === $key,
        ];
    }

    /**
     * One row per class and stream with the term's totals.
     *
     * @return Collection<int, object>
     */
    protected function totals(): Collection
    {
        if ($this->totals !== null) {
            return $this->totals;
        }

        $termId = $this->term()?->getKey() ?? 0;

        $students = Student::query()
            ->withoutGlobalScopes()
            ->where('students.school_id', $this->school()->getKey())
            ->where('status', StudentStatus::Active)
            ->select(['students.id', 'school_class_id', 'stream_id'])
            ->selectRaw('(select coalesce(sum(invoices.total), 0) from invoices where invoices.student_id = students.id and invoices.academic_term_id = ?) as billed', [$termId])
            ->selectRaw('(select coalesce(sum(payments.amount), 0) from payments where payments.student_id = students.id and payments.voided_at is null and payments.academic_term_id = ?) as paid', [$termId])
            ->selectRaw(Student::feeBalanceSql().' as balance');

        return $this->totals = DB::query()
            ->fromSub($students->toBase(), 't')
            ->selectRaw('school_class_id, stream_id, count(*) as students, sum(billed) as billed, sum(paid) as paid, sum(case when balance > 0 then balance else 0 end) as owed')
            ->groupBy('school_class_id', 'stream_id')
            ->get();
    }

    /**
     * @return array{type: string, class: SchoolClass, stream_id: int|null, label: string}|null
     */
    protected function groupContext(): ?array
    {
        if (! preg_match('/^([csn]):(\d+)$/', (string) $this->group, $match)) {
            return null;
        }

        [, $type, $id] = $match;
        $id = (int) $id;

        if ($type === 's') {
            $class = SchoolClass::query()->withoutGlobalScopes()
                ->where('school_id', $this->school()->getKey())
                ->whereHas('streams', fn (Builder $query) => $query->whereKey($id))
                ->with(['streams' => fn ($query) => $query->whereKey($id)])
                ->first();

            return $class ? ['type' => 's', 'class' => $class, 'stream_id' => $id, 'label' => $class->name.' '.$class->streams->first()->name] : null;
        }

        $class = SchoolClass::query()->withoutGlobalScopes()->where('school_id', $this->school()->getKey())->find($id);

        return $class ? ['type' => $type, 'class' => $class, 'stream_id' => null, 'label' => $class->name.($type === 'n' && $class->streams()->exists() ? ' (no stream)' : '')] : null;
    }

    protected function groupSummary(): ?string
    {
        if (! ($context = $this->groupContext())) {
            return null;
        }

        $tile = collect($this->tiles())->flatMap(fn (array $class): array => $class['tiles'])->firstWhere('key', $this->group);

        if (! $tile) {
            return null;
        }

        return collect([
            "{$tile['students']} ".str('student')->plural($tile['students']),
            'Fees '.Money::format($tile['billed']),
            'Paid '.Money::format($tile['paid']),
            'Still owed '.Money::format($tile['owed']),
        ])->implode(' · ');
    }

    /**
     * "Day UGX 950,000 · Boarding UGX 1,600,000": what the chosen class pays
     * this term according to the fee structure.
     */
    protected function feeSummary(): ?string
    {
        $context = $this->groupContext();
        $term = $this->term();

        if (! $context || ! $term) {
            return null;
        }

        $items = FeeItem::query()->withoutGlobalScopes()
            ->where('school_id', $this->school()->getKey())
            ->where('academic_term_id', $term->getKey())
            ->where(fn (Builder $query) => $query
                ->whereDoesntHave('classes')
                ->orWhereHas('classes', fn (Builder $query) => $query->whereKey($context['class']->getKey())))
            ->get();

        if ($items->isEmpty()) {
            return null;
        }

        $day = (int) $items->filter(fn (FeeItem $item): bool => in_array($item->residence?->value ?? $item->residence, [null, 'day'], true))->sum('amount');
        $boarding = (int) $items->filter(fn (FeeItem $item): bool => in_array($item->residence?->value ?? $item->residence, [null, 'boarding'], true))->sum('amount');

        return "{$context['class']->name} fees for {$term->name}: Day ".Money::format($day).($boarding !== $day ? ' · Boarding '.Money::format($boarding) : '');
    }

    protected function unbilledCount(): int
    {
        if (! ($term = $this->term()) || ! $this->groupContext()) {
            return 0;
        }

        $hasFees = FeeItem::query()->withoutGlobalScopes()->where('academic_term_id', $term->getKey())->exists();

        return $hasFees ? (clone $this->studentsQuery())->whereDoesntHave('invoices', fn (Builder $query) => $query->withoutGlobalScopes()->where('academic_term_id', $term->getKey()))->count() : 0;
    }

    protected function canBill(): bool
    {
        return Filament::auth()->user()?->can('create', Invoice::class) ?? false;
    }

    protected function term(): ?AcademicTerm
    {
        return $this->termId
            ? AcademicTerm::query()->withoutGlobalScopes()->where('school_id', $this->school()->getKey())->find($this->termId)
            : null;
    }

    protected function school(): School
    {
        /** @var School */
        return Filament::getTenant();
    }
}
