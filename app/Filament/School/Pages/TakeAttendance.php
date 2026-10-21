<?php

namespace App\Filament\School\Pages;

use App\Enums\AttendanceStatus;
use App\Enums\SchoolAbility;
use App\Enums\StudentStatus;
use App\Models\AttendanceRecord;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Support\TeacherScope;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * The daily class register. Choose the day and class, then mark each
 * student. Each choice saves straight away.
 */
class TakeAttendance extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;

    protected static string|UnitEnum|null $navigationGroup = 'Academics';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Attendance';

    protected static ?string $slug = 'attendance';

    /** @var array{date?: string|null, class_key?: string|null} */
    public ?array $selection = [];

    /** @var Collection<int, AttendanceRecord>|null */
    protected ?Collection $loadedRecords = null;

    protected ?TeacherScope $scope = null;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->canInSchool(SchoolAbility::TakeAttendance);
    }

    public function mount(): void
    {
        $this->selectionForm->fill([
            'date' => today()->toDateString(),
            'class_key' => collect($this->scope()->visibleClassOptions())->flatMap(fn (array $choices): array => array_keys($choices))->first(),
        ]);
    }

    public function getSubheading(): ?string
    {
        if (! $this->context()) {
            return 'Choose a day and a class.';
        }

        $total = $this->studentsQuery()->count();
        $records = $this->records();
        $present = $records->filter(fn (AttendanceRecord $record): bool => $record->status->isAtSchool())->count();
        $absent = $records->count() - $present;
        $missing = $total - $records->count();

        return "{$present} at school · {$absent} absent".($missing > 0 ? " · {$missing} not marked yet" : ' · Register complete');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedSchema::make('selectionForm'),
            EmbeddedTable::make(),
        ]);
    }

    public function selectionForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('selection')
            ->components([
                Section::make()
                    ->columns(['md' => 2])
                    ->schema([
                        DatePicker::make('date')
                            ->label('Day')
                            ->maxDate(today())
                            ->native(false)
                            ->displayFormat('D j M Y')
                            ->live()
                            ->afterStateUpdated(fn () => $this->selectionChanged()),
                        Select::make('class_key')
                            ->label('Class')
                            ->options(fn (): array => $this->scope()->visibleClassOptions())
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(fn () => $this->selectionChanged()),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->studentsQuery())
            ->columns([
                TextColumn::make('last_name')
                    ->label('Student')
                    ->formatStateUsing(fn (Student $record): string => $record->full_name)
                    ->description(fn (Student $record): ?string => trim($record->admission_number.' '.($record->stream ? '· '.$record->stream->name : '')))
                    ->searchable(['first_name', 'last_name', 'admission_number']),
                SelectColumn::make('attendance')
                    ->label('Attendance')
                    ->options(AttendanceStatus::class)
                    ->placeholder('Not marked')
                    ->state(fn (Student $record): ?string => $this->records()->get($record->getKey())?->status->value)
                    ->updateStateUsing(function (Student $record, $state): mixed {
                        $this->save($record, filled($state) ? AttendanceStatus::from($state) : null);

                        return $state;
                    }),
                TextInputColumn::make('note')
                    ->label('Note')
                    ->placeholder('e.g. sick, sent home for fees')
                    ->state(fn (Student $record): ?string => $this->records()->get($record->getKey())?->note)
                    ->rules(['nullable', 'max:255'])
                    ->disabled(fn (Student $record): bool => ! $this->records()->has($record->getKey()))
                    ->updateStateUsing(function (Student $record, $state): mixed {
                        $existing = $this->records()->get($record->getKey());
                        abort_unless($existing && $this->context(), 403);

                        $existing->update(['note' => filled($state) ? trim((string) $state) : null]);
                        $this->loadedRecords = null;

                        return $state;
                    })
                    ->visibleFrom('md'),
            ])
            ->defaultSort(fn (Builder $query): Builder => $query->orderBy('last_name')->orderBy('first_name'))
            ->paginated([50, 100, 'all'])
            ->defaultPaginationPageOption(100)
            ->emptyStateHeading(fn (): string => $this->context() ? 'No students in this class' : 'Choose a day and a class');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markRestPresent')
                ->label('Mark the rest present')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->visible(fn (): bool => $this->context() !== null)
                ->action(function (): void {
                    $marked = 0;

                    foreach ($this->studentsQuery()->get() as $student) {
                        if (! $this->records()->has($student->getKey())) {
                            $this->save($student, AttendanceStatus::Present);
                            $marked++;
                        }
                    }

                    Notification::make()
                        ->title($marked > 0 ? "Marked {$marked} ".str('student')->plural($marked).' present' : 'Everyone is already marked')
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function save(Student $student, ?AttendanceStatus $status): void
    {
        [$date] = $this->context() ?? [null];
        abort_unless($date && $this->studentsQuery()->whereKey($student->getKey())->exists(), 403);

        AttendanceRecord::record($student, $date->toDateString(), $status, by: Filament::auth()->user());
        $this->loadedRecords = null;
    }

    /**
     * The checked selection: [day, class, stream id], or null while
     * incomplete, in the future or not allowed.
     *
     * @return array{0: CarbonImmutable, 1: SchoolClass, 2: int|null}|null
     */
    protected function context(): ?array
    {
        $date = filled($this->selection['date'] ?? null) ? CarbonImmutable::parse($this->selection['date'])->startOfDay() : null;
        [$classId, $streamId] = TeacherScope::parseKey((string) ($this->selection['class_key'] ?? ''));

        if (! $date || $date->isFuture() || ! $classId || ! $this->scope()->canSeeClass($classId, $streamId)) {
            return null;
        }

        $class = SchoolClass::query()->withoutGlobalScopes()->where('school_id', $this->school()->getKey())->find($classId);

        return $class ? [$date, $class, $streamId] : null;
    }

    protected function studentsQuery(): Builder
    {
        $query = Student::query()
            ->withoutGlobalScopes()
            ->with('stream')
            ->where('school_id', $this->school()->getKey())
            ->where('status', StudentStatus::Active);

        $context = $this->context();

        if (! $context) {
            return $query->whereRaw('1 = 0');
        }

        [, $class, $streamId] = $context;
        $query->where('school_class_id', $class->getKey());

        if ($streamId) {
            $query->where('stream_id', $streamId);
        }

        return $query;
    }

    /**
     * @return Collection<int, AttendanceRecord> by student id
     */
    protected function records(): Collection
    {
        if ($this->loadedRecords !== null) {
            return $this->loadedRecords;
        }

        $context = $this->context();

        return $this->loadedRecords = $context
            ? AttendanceRecord::query()->withoutGlobalScopes()
                ->where('school_id', $this->school()->getKey())
                ->whereDate('date', $context[0])
                ->whereIn('student_id', $this->studentsQuery()->select('id'))
                ->get()
                ->keyBy('student_id')
            : collect();
    }

    protected function selectionChanged(): void
    {
        $this->loadedRecords = null;
        $this->resetPage();
    }

    protected function scope(): TeacherScope
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        return $this->scope ??= TeacherScope::for($user, $this->school());
    }

    protected function school(): School
    {
        /** @var School */
        return Filament::getTenant();
    }
}
