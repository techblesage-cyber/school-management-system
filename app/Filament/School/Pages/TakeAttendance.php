<?php

namespace App\Filament\School\Pages;

use App\Enums\AttendanceStatus;
use App\Enums\SchoolAbility;
use App\Enums\StudentStatus;
use App\Filament\Shared\InitialsAvatars;
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
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * The daily class register. Choose the day and the class or stream; every
 * student is listed with one button for each choice (Present, Absent,
 * Sick, Late, Permission). One tap saves it.
 */
class TakeAttendance extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;

    protected static string|UnitEnum|null $navigationGroup = 'Academics';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Attendance';

    protected static ?string $slug = 'attendance';

    /** @var array{date?: string|null, class_key?: string|null} */
    public ?array $selection = [];

    public string $search = '';

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
            'class_key' => $this->defaultClassKey(),
        ]);
    }

    public function getSubheading(): ?string
    {
        return $this->context()
            ? 'Tap one button for each student. It saves straight away.'
            : 'Choose a day and a class.';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedSchema::make('selectionForm'),
            View::make('filament.pages.attendance-register')
                ->viewData(fn (): array => $this->registerData()),
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
                            ->afterStateUpdated(fn () => $this->loadedRecords = null),
                        Select::make('class_key')
                            ->label('Class or stream')
                            ->options(fn (): array => $this->scope()->visibleClassOptions())
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(fn () => $this->loadedRecords = null),
                    ]),
            ]);
    }

    /**
     * Saves one student's attendance for the chosen day.
     */
    public function mark(int $studentId, string $status): void
    {
        $choice = AttendanceStatus::tryFrom($status);
        $student = $this->studentsQuery()->find($studentId);

        abort_unless($choice && $student && $this->context(), 403);

        $this->save($student, $choice);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markRestPresent')
                ->label('Mark the rest present')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
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

    /**
     * @return array<string, mixed>
     */
    protected function registerData(): array
    {
        if (! $this->context()) {
            return ['ready' => false];
        }

        $students = $this->studentsQuery()
            ->when(filled(trim($this->search)), fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('first_name', 'like', '%'.trim($this->search).'%')
                ->orWhere('last_name', 'like', '%'.trim($this->search).'%')
                ->orWhere('admission_number', 'like', '%'.trim($this->search).'%')))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $records = $this->records();
        $total = $this->studentsQuery()->count();
        $counts = collect(AttendanceStatus::cases())
            ->mapWithKeys(fn (AttendanceStatus $status): array => [$status->value => $records->filter(fn (AttendanceRecord $record): bool => $record->status === $status)->count()]);

        return [
            'ready' => true,
            'statuses' => AttendanceStatus::cases(),
            'rows' => $students->map(fn (Student $student): array => [
                'id' => $student->getKey(),
                'name' => $student->full_name,
                'sub' => collect([$student->admission_number, $student->stream?->name])->filter()->implode(' · '),
                'photo' => $student->photoUrl() ?? InitialsAvatars::for($student->full_name),
                'status' => $records->get($student->getKey())?->status?->value,
            ])->all(),
            'counts' => $counts->all(),
            'notMarked' => max(0, $total - $records->count()),
            'searching' => filled(trim($this->search)),
            'total' => $total,
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
     * A class teacher starts on their own class or stream; others on the
     * first class they can see.
     */
    protected function defaultClassKey(): ?string
    {
        $options = collect($this->scope()->visibleClassOptions())->flatMap(fn (array $choices): array => array_keys($choices));
        $user = Filament::auth()->user();

        $own = SchoolClass::query()->withoutGlobalScopes()
            ->where('school_id', $this->school()->getKey())
            ->with('streams')
            ->get()
            ->flatMap(fn (SchoolClass $class): array => [
                ...($class->class_teacher_id === $user?->getKey() ? ['c:'.$class->getKey()] : []),
                ...$class->streams->where('class_teacher_id', $user?->getKey())->map(fn ($stream): string => 's:'.$stream->getKey())->all(),
            ])
            ->first(fn (string $key): bool => $options->contains($key));

        return $own ?? $options->first();
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
