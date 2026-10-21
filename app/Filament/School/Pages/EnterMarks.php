<?php

namespace App\Filament\School\Pages;

use App\Enums\SchoolAbility;
use App\Enums\StudentStatus;
use App\Filament\School\Resources\GradingScales\GradingScaleResource;
use App\Models\AcademicTerm;
use App\Models\Assessment;
use App\Models\GradingScale;
use App\Models\Mark;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Support\TeacherScope;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Teachers type marks straight into the list: choose the exam, subject and
 * class, then enter each student's score. Each mark saves as soon as the box
 * is left. Teachers only see the subjects and classes they teach.
 */
class EnterMarks extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static string|UnitEnum|null $navigationGroup = 'Academics';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Enter marks';

    protected static ?string $slug = 'enter-marks';

    /** @var array{assessment_id?: int|string|null, subject_id?: int|string|null, class_key?: string|null} */
    public ?array $selection = [];

    /** @var Collection<int, Mark>|null marks of the current selection, by student id */
    protected ?Collection $loadedMarks = null;

    protected ?TeacherScope $scope = null;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->canInSchool(SchoolAbility::EnterMarks);
    }

    public function mount(): void
    {
        $this->selectionForm->fill([
            'assessment_id' => array_key_first(array_values($this->assessmentOptions())[0] ?? []),
        ]);
    }

    public function getSubheading(): ?string
    {
        $context = $this->context();

        if (! $context) {
            return 'Choose an exam, a subject and a class. Marks save as you go.';
        }

        [$assessment, , $class] = $context;
        $students = $this->studentsQuery()->count();
        $marked = $this->marks()->count();

        return "Out of {$assessment->max_score} · {$marked} of {$students} students marked"
            .($class->grading_scale_id ? '' : ' · This class has no grading scale yet, so no grades are shown');
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
                    ->columns(['md' => 3])
                    ->schema([
                        Select::make('assessment_id')
                            ->label('Exam')
                            ->options(fn (): array => $this->assessmentOptions())
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(fn () => $this->selectionChanged()),
                        Select::make('subject_id')
                            ->label('Subject')
                            ->options(fn (): array => $this->scope()->markableSubjects())
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('class_key', null);
                                $this->selectionChanged();
                            }),
                        Select::make('class_key')
                            ->label('Class')
                            ->options(fn (): array => $this->classOptions())
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
                TextColumn::make('admission_number')
                    ->label('Adm. no.')
                    ->visibleFrom('md'),
                TextColumn::make('last_name')
                    ->label('Student')
                    ->formatStateUsing(fn (Student $record): string => $record->full_name)
                    ->description(fn (Student $record): ?string => $record->stream?->name)
                    ->searchable(['first_name', 'last_name', 'admission_number']),
                TextInputColumn::make('score')
                    ->label(fn (): string => 'Score'.(($assessment = $this->context()[0] ?? null) ? " (/{$assessment->max_score})" : ''))
                    ->state(fn (Student $record): ?string => ($mark = $this->marks()->get($record->getKey()))
                        ? GradingScaleResource::number($mark->score)
                        : null)
                    ->inputMode('decimal')
                    ->rules(fn (): array => ['numeric', 'min:0', 'max:'.($this->context()[0]->max_score ?? 100)])
                    ->disabled(fn (Student $record): bool => ! $this->canMark($record))
                    ->updateStateUsing(fn (Student $record, $state) => $this->saveMark($record, $state)),
                TextColumn::make('grade')
                    ->state(function (Student $record): ?string {
                        $mark = $this->marks()->get($record->getKey());
                        [$assessment, , $class] = $this->context() ?? [null, null, null];

                        if (! $mark || ! $assessment || ! $class?->grading_scale_id) {
                            return null;
                        }

                        return $this->scale($class)?->band($mark->score / $assessment->max_score * 100)?->grade;
                    })
                    ->badge()
                    ->placeholder('—'),
            ])
            ->defaultSort(fn (Builder $query): Builder => $query->orderBy('last_name')->orderBy('first_name'))
            ->paginated([25, 50, 100, 'all'])
            ->defaultPaginationPageOption(50)
            ->emptyStateHeading(fn (): string => $this->context() ? 'No students in this class' : 'Choose an exam, subject and class')
            ->emptyStateDescription(fn (): ?string => $this->context() ? null : 'The students appear here, ready for their marks.');
    }

    /**
     * Saves (or clears) one mark, after checking the teacher may mark this
     * student in this subject.
     */
    protected function saveMark(Student $student, mixed $state): ?string
    {
        $context = $this->context();

        abort_unless($context && $this->canMark($student), 403);

        [$assessment, $subjectId] = $context;
        $key = ['assessment_id' => $assessment->getKey(), 'student_id' => $student->getKey(), 'subject_id' => $subjectId];

        if ($state === null || trim((string) $state) === '') {
            Mark::query()->where($key)->delete();
        } else {
            Mark::query()->updateOrCreate($key, [
                'school_id' => $student->school_id,
                'school_class_id' => $student->school_class_id,
                'stream_id' => $student->stream_id,
                'score' => round((float) $state, 2),
                'entered_by' => Filament::auth()->id(),
            ]);
        }

        $this->loadedMarks = null;

        return $state;
    }

    protected function canMark(Student $student): bool
    {
        $context = $this->context();

        return $context !== null
            && $student->status === StudentStatus::Active
            && $this->scope()->canMarkStudent((int) $context[1], $student);
    }

    /**
     * The checked selection: [assessment, subject id, class, stream id], or
     * null while incomplete or not allowed.
     *
     * @return array{0: Assessment, 1: int, 2: SchoolClass, 3: int|null}|null
     */
    protected function context(): ?array
    {
        $assessmentId = (int) ($this->selection['assessment_id'] ?? 0);
        $subjectId = (int) ($this->selection['subject_id'] ?? 0);
        $classKey = (string) ($this->selection['class_key'] ?? '');

        if (! $assessmentId || ! $subjectId || $classKey === '') {
            return null;
        }

        $assessment = Assessment::query()->withoutGlobalScopes()
            ->where('school_id', $this->school()->getKey())
            ->find($assessmentId);

        [$classId, $streamId] = TeacherScope::parseKey($classKey);
        $allowedKeys = collect($this->classOptions())->flatMap(fn (array $choices): array => array_keys($choices));

        if (! $assessment || ! $classId || ! $allowedKeys->contains($classKey) || ! array_key_exists($subjectId, $this->scope()->markableSubjects())) {
            return null;
        }

        $class = SchoolClass::query()->withoutGlobalScopes()->where('school_id', $this->school()->getKey())->find($classId);

        return $class ? [$assessment, $subjectId, $class, $streamId] : null;
    }

    protected function studentsQuery(): Builder
    {
        $context = $this->context();

        $query = Student::query()
            ->withoutGlobalScopes()
            ->with('stream')
            ->where('school_id', $this->school()->getKey())
            ->where('status', StudentStatus::Active);

        if (! $context) {
            return $query->whereRaw('1 = 0');
        }

        [, $subjectId, $class, $streamId] = $context;

        $query->where('school_class_id', $class->getKey());

        if ($streamId) {
            $query->where('stream_id', $streamId);
        }

        // A teacher of only some streams sees just those streams, even when
        // the whole class is chosen
        if (! $this->scope()->canMark($subjectId, $class->getKey(), null)) {
            $query->whereIn('stream_id', $class->streams()->pluck('id')
                ->filter(fn (int $id): bool => $this->scope()->canMark($subjectId, $class->getKey(), $id))
                ->values()
                ->all());
        }

        return $query;
    }

    /**
     * @return Collection<int, Mark>
     */
    protected function marks(): Collection
    {
        if ($this->loadedMarks !== null) {
            return $this->loadedMarks;
        }

        $context = $this->context();

        return $this->loadedMarks = $context
            ? Mark::query()
                ->where('assessment_id', $context[0]->getKey())
                ->where('subject_id', $context[1])
                ->get()
                ->keyBy('student_id')
            : collect();
    }

    /**
     * Exams of the current and recent terms, newest term first.
     *
     * @return array<string, array<int, string>>
     */
    protected function assessmentOptions(): array
    {
        $current = AcademicTerm::currentFor($this->school());

        return Assessment::query()->withoutGlobalScopes()
            ->with('academicTerm')
            ->where('school_id', $this->school()->getKey())
            ->get()
            ->sortBy([
                fn (Assessment $a, Assessment $b): int => [$b->academicTerm->getKey() === $current?->getKey(), $b->academicTerm->year, $b->academicTerm->term]
                    <=> [$a->academicTerm->getKey() === $current?->getKey(), $a->academicTerm->year, $a->academicTerm->term],
                fn (Assessment $a, Assessment $b): int => [$a->sort_order, $a->getKey()] <=> [$b->sort_order, $b->getKey()],
            ])
            ->groupBy(fn (Assessment $assessment): string => $assessment->academicTerm->name)
            ->map(fn (Collection $assessments): array => $assessments->mapWithKeys(fn (Assessment $a): array => [$a->getKey() => "{$a->name} (out of {$a->max_score})"])->all())
            ->all();
    }

    /**
     * Classes the teacher may mark in the chosen subject, limited to those
     * that sit the chosen exam.
     *
     * @return array<string, array<string, string>>
     */
    protected function classOptions(): array
    {
        $subjectId = (int) ($this->selection['subject_id'] ?? 0);

        if (! $subjectId) {
            return [];
        }

        $options = $this->scope()->markableClassOptions($subjectId);
        $assessment = Assessment::query()->withoutGlobalScopes()->with('classes')
            ->where('school_id', $this->school()->getKey())
            ->find((int) ($this->selection['assessment_id'] ?? 0));

        if ($assessment && $assessment->classes->isNotEmpty()) {
            $names = $assessment->classes->pluck('name')->all();
            $options = array_intersect_key($options, array_flip($names));
        }

        return $options;
    }

    protected function selectionChanged(): void
    {
        $this->loadedMarks = null;
        $this->resetPage();
    }

    /** @var array<int, GradingScale|null> */
    protected array $scales = [];

    protected function scale(SchoolClass $class): ?GradingScale
    {
        $id = (int) $class->grading_scale_id;

        if (! array_key_exists($id, $this->scales)) {
            $this->scales[$id] = GradingScale::query()->withoutGlobalScopes()->with('bands')->find($id);
        }

        return $this->scales[$id];
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
