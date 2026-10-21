<?php

namespace App\Filament\School\Pages;

use App\Enums\ResultSummary;
use App\Enums\SchoolAbility;
use App\Models\AcademicTerm;
use App\Models\ReportComment;
use App\Models\ResultPublication;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\Sms\Sms;
use App\Support\Results\StudentResult;
use App\Support\Results\TermResults;
use App\Support\TeacherScope;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * A class's results for a term: each student's average, grade or division,
 * and position. Write comments, print report cards, and publish them to
 * parents.
 */
class ReportCards extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Academics';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Report cards';

    protected static ?string $slug = 'report-cards';

    /** @var array{academic_term_id?: int|string|null, class_key?: string|null} */
    public ?array $selection = [];

    protected ?TermResults $results = null;

    protected ?string $resultsKey = null;

    protected ?TeacherScope $scope = null;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->canInSchool(SchoolAbility::ViewResults);
    }

    public function mount(): void
    {
        $firstClass = collect($this->scope()->visibleClassOptions())->flatMap(fn (array $choices): array => array_keys($choices))->first();

        $this->selectionForm->fill([
            'academic_term_id' => AcademicTerm::currentFor($this->school())?->getKey(),
            'class_key' => $firstClass,
        ]);
    }

    public function getSubheading(): ?string
    {
        [$term, $class] = $this->context() ?? [null, null];

        if (! $term || ! $class) {
            return 'Choose a term and a class.';
        }

        $published = ResultPublication::query()->withoutGlobalScopes()
            ->where('academic_term_id', $term->getKey())
            ->where('school_class_id', $class->getKey())
            ->first();

        return collect([
            $published
                ? 'Published to parents on '.$published->created_at->format('j M Y')
                : 'Not published yet: parents can\'t see these report cards.',
            $class->grading_scale_id ? null : 'This class has no grading scale, so no grades are shown (set one under Setup → Classes & streams).',
        ])->filter()->implode(' ');
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
                        Select::make('academic_term_id')
                            ->label('Term')
                            ->options(fn (): array => AcademicTerm::optionsFor($this->school()))
                            ->live()
                            ->afterStateUpdated(fn () => $this->resetPage()),
                        Select::make('class_key')
                            ->label('Class')
                            ->options(fn (): array => $this->scope()->visibleClassOptions())
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(fn () => $this->resetPage()),
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
                    ->visibleFrom('lg'),
                TextColumn::make('last_name')
                    ->label('Student')
                    ->formatStateUsing(fn (Student $record): string => $record->full_name)
                    ->description(fn (Student $record): ?string => $this->resultFor($record)?->classLabel)
                    ->searchable(['first_name', 'last_name', 'admission_number']),
                TextColumn::make('result')
                    ->label('Result')
                    ->state(fn (Student $record): ?string => self::summaryLine($this->resultFor($record)))
                    ->placeholder('No marks'),
                TextColumn::make('position')
                    ->label('Position')
                    ->state(fn (Student $record): ?string => ($result = $this->resultFor($record)) && $result->position
                        ? "{$result->position} of {$result->outOf}"
                        : null)
                    ->placeholder('—')
                    ->visibleFrom('md'),
                IconColumn::make('commented')
                    ->label('Comments')
                    ->state(fn (Student $record): bool => filled($this->resultFor($record)?->classTeacherComment) && filled($this->resultFor($record)?->headTeacherComment))
                    ->boolean()
                    ->visibleFrom('md'),
            ])
            ->defaultSort(fn (Builder $query): Builder => $query->orderBy('last_name')->orderBy('first_name'))
            ->paginated([25, 50, 100, 'all'])
            ->defaultPaginationPageOption(50)
            ->recordActions([
                Action::make('print')
                    ->label('Report card')
                    ->tooltip('Print report card')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->color('gray')
                    ->button()
                    ->size('sm')
                    ->labeledFrom('md')
                    ->url(fn (Student $record): ?string => $this->printUrl($record))
                    ->openUrlInNewTab(),
                $this->commentsAction(),
            ])
            ->emptyStateHeading(fn (): string => $this->context() ? 'No students in this class' : 'Choose a term and a class');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('printClass')
                ->label('Print all')
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->visible(fn (): bool => $this->context() !== null)
                ->url(fn (): ?string => $this->printUrl())
                ->openUrlInNewTab(),
            Action::make('suggestComments')
                ->label('Suggest comments')
                ->icon(Heroicon::OutlinedSparkles)
                ->color('gray')
                ->visible(fn (): bool => $this->context() !== null)
                ->requiresConfirmation()
                ->modalDescription('Fills in empty class teacher comments with a suggestion based on each student\'s average. Comments already written are kept. You can edit any of them afterwards.')
                ->action(fn () => $this->suggestComments()),
            Action::make('publish')
                ->label('Publish to parents')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->visible(fn (): bool => $this->context() !== null && ! $this->isPublished() && $this->canPublish())
                ->requiresConfirmation()
                ->modalDescription('Parents of this class will see these report cards in the parents\' portal. Marks and comments changed afterwards show up straight away.')
                ->schema(fn (): array => Sms::enabledFor($this->school()) ? [
                    Toggle::make('notify')
                        ->label('Also tell parents by SMS')
                        ->default(true),
                ] : [])
                ->action(function (array $data): void {
                    [$term, $class] = $this->context();

                    ResultPublication::query()->withoutGlobalScopes()->firstOrCreate(
                        ['academic_term_id' => $term->getKey(), 'school_class_id' => $class->getKey()],
                        ['school_id' => $this->school()->getKey(), 'published_by' => Filament::auth()->id()],
                    );

                    $texted = ($data['notify'] ?? false)
                        ? Sms::reportCardsReady(
                            $this->school(),
                            $this->results()->students->filter(fn (StudentResult $result): bool => $result->hasMarks())->map->student,
                            $term->name,
                        )
                        : 0;

                    Notification::make()
                        ->title("{$class->name} report cards published")
                        ->body($texted > 0 ? "Texting {$texted} ".str('parent')->plural($texted).'.' : null)
                        ->success()
                        ->send();
                }),
            Action::make('unpublish')
                ->label('Unpublish')
                ->icon(Heroicon::OutlinedEyeSlash)
                ->color('gray')
                ->visible(fn (): bool => $this->context() !== null && $this->isPublished() && $this->canPublish())
                ->requiresConfirmation()
                ->modalDescription('Parents will no longer see these report cards until you publish them again.')
                ->action(function (): void {
                    [$term, $class] = $this->context();

                    ResultPublication::query()->withoutGlobalScopes()
                        ->where('academic_term_id', $term->getKey())
                        ->where('school_class_id', $class->getKey())
                        ->delete();

                    Notification::make()->title("{$class->name} report cards hidden from parents")->success()->send();
                }),
        ];
    }

    protected function commentsAction(): Action
    {
        return Action::make('comments')
            ->label('Comments')
            ->tooltip('Write comments')
            ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
            ->color('gray')
            ->button()
            ->size('sm')
            ->labeledFrom('md')
            ->visible(fn (Student $record): bool => $this->canWriteClassTeacherComment($record) || $this->canWriteHeadTeacherComment())
            ->modalHeading(fn (Student $record): string => "Comments for {$record->full_name}")
            ->modalDescription(fn (Student $record): ?string => self::summaryLine($this->resultFor($record)))
            ->fillForm(fn (Student $record): array => [
                'class_teacher_comment' => $this->resultFor($record)?->classTeacherComment,
                'head_teacher_comment' => $this->resultFor($record)?->headTeacherComment,
            ])
            ->schema(fn (Student $record): array => [
                Textarea::make('class_teacher_comment')
                    ->label('Class teacher\'s comment')
                    ->rows(3)
                    ->maxLength(500)
                    ->disabled(! $this->canWriteClassTeacherComment($record))
                    ->hintAction(
                        Action::make('suggest')
                            ->label('Suggest')
                            ->icon(Heroicon::OutlinedSparkles)
                            ->visible($this->canWriteClassTeacherComment($record))
                            ->action(fn (Set $set) => $set('class_teacher_comment', $this->resultFor($record)?->suggestedComment())),
                    ),
                Textarea::make('head_teacher_comment')
                    ->label('Head teacher\'s comment')
                    ->rows(3)
                    ->maxLength(500)
                    ->disabled(! $this->canWriteHeadTeacherComment()),
            ])
            ->action(function (Student $record, array $data): void {
                [$term] = $this->context() ?? [null];
                abort_unless($term && $this->resultFor($record), 403);

                $changes = [];

                if ($this->canWriteClassTeacherComment($record) && array_key_exists('class_teacher_comment', $data)) {
                    $changes['class_teacher_comment'] = $data['class_teacher_comment'];
                }

                if ($this->canWriteHeadTeacherComment() && array_key_exists('head_teacher_comment', $data)) {
                    $changes['head_teacher_comment'] = $data['head_teacher_comment'];
                }

                if ($changes !== []) {
                    ReportComment::query()->withoutGlobalScopes()->updateOrCreate(
                        ['student_id' => $record->getKey(), 'academic_term_id' => $term->getKey()],
                        ['school_id' => $this->school()->getKey(), ...$changes],
                    );
                }

                $this->results = null;

                Notification::make()->title('Comments saved')->success()->send();
            });
    }

    protected function suggestComments(): void
    {
        [$term] = $this->context();
        $filled = 0;

        foreach ($this->results()->students as $result) {
            if (! $result->hasMarks() || filled($result->classTeacherComment) || ! $this->canWriteClassTeacherComment($result->student)) {
                continue;
            }

            ReportComment::query()->withoutGlobalScopes()->updateOrCreate(
                ['student_id' => $result->student->getKey(), 'academic_term_id' => $term->getKey()],
                ['school_id' => $this->school()->getKey(), 'class_teacher_comment' => $result->suggestedComment()],
            );
            $filled++;
        }

        $this->results = null;

        Notification::make()
            ->title($filled > 0 ? "Suggested {$filled} ".str('comment')->plural($filled) : 'No empty comments to fill')
            ->success()
            ->send();
    }

    /**
     * "Average 72.5% · B", "Aggregate 8 · Division I" or "18 points".
     */
    public static function summaryLine(?StudentResult $result): ?string
    {
        if (! $result || ! $result->hasMarks()) {
            return null;
        }

        return match (true) {
            $result->aggregate !== null => "Aggregate {$result->aggregate} · {$result->division}",
            $result->points !== null => TermResults::number($result->points).' points · Average '.TermResults::number($result->average).'%',
            default => trim('Average '.TermResults::number($result->average).'% '.($result->grade ? "· {$result->grade}" : '')),
        };
    }

    protected function resultFor(Student $student): ?StudentResult
    {
        return $this->context() ? $this->results()->students->get($student->getKey()) : null;
    }

    protected function results(): TermResults
    {
        [$term, $class] = $this->context();
        $key = $term->getKey().'-'.$class->getKey();

        if ($this->results === null || $this->resultsKey !== $key) {
            $this->results = TermResults::forClass($term, $class);
            $this->resultsKey = $key;
        }

        return $this->results;
    }

    /**
     * The checked selection: [term, class, stream id], or null while
     * incomplete or not allowed.
     *
     * @return array{0: AcademicTerm, 1: SchoolClass, 2: int|null}|null
     */
    protected function context(): ?array
    {
        $termId = (int) ($this->selection['academic_term_id'] ?? 0);
        $classKey = (string) ($this->selection['class_key'] ?? '');
        [$classId, $streamId] = TeacherScope::parseKey($classKey);

        if (! $termId || ! $classId || ! $this->scope()->canSeeClass($classId, $streamId)) {
            return null;
        }

        $term = AcademicTerm::query()->withoutGlobalScopes()->where('school_id', $this->school()->getKey())->find($termId);
        $class = SchoolClass::query()->withoutGlobalScopes()->where('school_id', $this->school()->getKey())->find($classId);

        return $term && $class ? [$term, $class, $streamId] : null;
    }

    protected function studentsQuery(): Builder
    {
        $query = Student::query()->withoutGlobalScopes()->where('school_id', $this->school()->getKey());
        $context = $this->context();

        if (! $context) {
            return $query->whereRaw('1 = 0');
        }

        [, , $streamId] = $context;
        $results = $this->results()->students;

        if ($streamId) {
            $results = $results->filter(fn (StudentResult $result): bool => $result->streamId === $streamId);
        } elseif (! $this->scope()->unrestricted && ! $this->scope()->canSeeClass($context[1]->getKey(), null)) {
            $results = collect();
        }

        return $query->whereIn('id', $results->keys()->all());
    }

    protected function isPublished(): bool
    {
        [$term, $class] = $this->context() ?? [null, null];

        return $term && ResultPublication::isPublished($term->getKey(), $class->getKey());
    }

    protected function canPublish(): bool
    {
        return $this->user()->canInSchool(SchoolAbility::ManageExams, $this->school());
    }

    protected function canWriteHeadTeacherComment(): bool
    {
        return $this->user()->canInSchool(SchoolAbility::ManageExams, $this->school());
    }

    protected function canWriteClassTeacherComment(Student $student): bool
    {
        $result = $this->resultFor($student);
        [, $class] = $this->context() ?? [null, null];

        return $result !== null && $class !== null
            && $this->scope()->canWriteClassTeacherComment($class->getKey(), $result->streamId);
    }

    /**
     * Report card(s) to print: one student, or the whole selection.
     */
    protected function printUrl(?Student $student = null): ?string
    {
        [$term, $class, $streamId] = $this->context() ?? [null, null, null];

        if (! $term) {
            return null;
        }

        return route('reports.print', array_filter([
            'term' => $term->getKey(),
            'class' => $class->getKey(),
            'stream' => $streamId,
            'student' => $student?->getKey(),
        ]), absolute: false);
    }

    protected function scope(): TeacherScope
    {
        return $this->scope ??= TeacherScope::for($this->user(), $this->school());
    }

    protected function user(): User
    {
        /** @var User */
        return Filament::auth()->user();
    }

    protected function school(): School
    {
        /** @var School */
        return Filament::getTenant();
    }
}
