<?php

namespace Tests\Feature;

use App\Enums\StaffRole;
use App\Filament\School\Pages\EnterMarks;
use App\Filament\School\Pages\ReportCards;
use App\Filament\School\Resources\Assessments\Pages\ManageAssessments;
use App\Filament\School\Resources\GradingScales\Pages\ListGradingScales;
use App\Models\AcademicTerm;
use App\Models\Assessment;
use App\Models\GradingScale;
use App\Models\Mark;
use App\Models\ReportComment;
use App\Models\ResultPublication;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Stream;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Support\Results\TermResults;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExamsReportCardsTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $head;

    private AcademicTerm $term;

    private SchoolClass $p7;

    private Stream $east;

    private Stream $west;

    /** @var array<string, Subject> */
    private array $subjects = [];

    private Assessment $mid;

    private Assessment $end;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::factory()->create(['slug' => 'stmarys', 'name' => 'St Marys Primary']);
        $this->head = $this->staff(StaffRole::HeadTeacher, 'Sarah Head');
        $this->signInAs($this->head);

        $this->term = AcademicTerm::factory()->create(['school_id' => $this->school->getKey(), 'year' => 2026, 'term' => 3]);
        $scale = GradingScale::addStandard($this->school, 'Primary (D1–F9)');
        $this->p7 = SchoolClass::factory()->create(['school_id' => $this->school->getKey(), 'name' => 'P.7', 'level' => 'primary', 'grading_scale_id' => $scale->getKey()]);
        $this->east = $this->p7->streams()->create(['name' => 'East']);
        $this->west = $this->p7->streams()->create(['name' => 'West']);

        foreach (['English', 'Mathematics', 'Science', 'Social Studies', 'Religious Education'] as $name) {
            $this->subjects[$name] = Subject::factory()->create([
                'school_id' => $this->school->getKey(),
                'name' => $name,
                'in_aggregate' => $name !== 'Religious Education',
            ]);
        }

        // Mid-term counts 30, end of term 70
        $this->mid = Assessment::create(['school_id' => $this->school->getKey(), 'academic_term_id' => $this->term->getKey(), 'name' => 'Mid-term', 'max_score' => 50, 'weight' => 30, 'sort_order' => 1]);
        $this->end = Assessment::create(['school_id' => $this->school->getKey(), 'academic_term_id' => $this->term->getKey(), 'name' => 'End of term', 'max_score' => 100, 'weight' => 70, 'sort_order' => 2]);
    }

    private function staff(StaffRole $role, ?string $name = null, ?School $school = null): User
    {
        $user = User::factory()->create($name ? ['name' => $name] : []);
        ($school ?? $this->school)->users()->attach($user, ['role' => $role->value]);

        return $user;
    }

    private function signInAs(User $user): void
    {
        $this->actingAs($user);
        Filament::setCurrentPanel('school');
        Filament::setTenant($this->school);
        Filament::bootCurrentPanel();
    }

    private function pupil(Stream $stream, string $first, string $last): Student
    {
        return Student::factory()->create([
            'school_id' => $this->school->getKey(),
            'school_class_id' => $this->p7->getKey(),
            'stream_id' => $stream->getKey(),
            'first_name' => $first,
            'last_name' => $last,
        ]);
    }

    /**
     * @param  array<string, array{0: float|null, 1: float|null}>  $scores  subject => [mid /50, end /100]
     */
    private function marks(Student $student, array $scores): void
    {
        foreach ($scores as $subject => [$mid, $end]) {
            foreach ([[$this->mid, $mid], [$this->end, $end]] as [$assessment, $score]) {
                if ($score !== null) {
                    Mark::create([
                        'school_id' => $this->school->getKey(),
                        'assessment_id' => $assessment->getKey(),
                        'student_id' => $student->getKey(),
                        'subject_id' => $this->subjects[$subject]->getKey(),
                        'school_class_id' => $student->school_class_id,
                        'stream_id' => $student->stream_id,
                        'score' => $score,
                    ]);
                }
            }
        }
    }

    private function assign(User $teacher, string $subject, string $classKey): void
    {
        $rows = TeachingAssignment::rowsFor($this->school, $teacher);
        $rows[] = ['subject_id' => $this->subjects[$subject]->getKey(), 'classes' => [$classKey]];
        TeachingAssignment::syncFor($this->school, $teacher, $rows);
    }

    public function test_standard_scales_and_usual_exams_are_added_once(): void
    {
        Livewire::test(ListGradingScales::class)
            ->callAction('addStandardScales', data: ['scales' => ['Primary (D1–F9)', 'O-level (A–E)', 'A-level (A–F)']])
            ->assertHasNoActionErrors();

        $this->assertSame(3, $this->school->gradingScales()->count());
        $olevel = $this->school->gradingScales()->where('name', 'O-level (A–E)')->first();
        $this->assertSame('A', $olevel->band(90)->grade);
        $this->assertSame('E', $olevel->band(12)->grade);

        $primary = $this->school->gradingScales()->where('name', 'Primary (D1–F9)')->with('bands')->first();
        $this->assertSame('D1', $primary->band(80)->grade);
        $this->assertSame('D2', $primary->band(79.99)->grade);
        $this->assertSame('Division I', $primary->division(4));
        $this->assertSame('Division I', $primary->division(12));
        $this->assertSame('Division II', $primary->division(13));
        $this->assertSame('Division IV', $primary->division(33));
        $this->assertSame('Ungraded (U)', $primary->division(34));

        $term = AcademicTerm::factory()->create(['school_id' => $this->school->getKey(), 'year' => 2027, 'term' => 1, 'is_current' => true]);

        Livewire::test(ManageAssessments::class)
            ->callAction('addUsualExams', data: ['academic_term_id' => $term->getKey()])
            ->callAction('addUsualExams', data: ['academic_term_id' => $term->getKey()]);

        $this->assertSame(['Beginning of term', 'Mid-term', 'End of term'], $term->assessments()->orderBy('sort_order')->pluck('name')->all());
    }

    public function test_term_marks_combine_exams_by_weight_and_give_grades_aggregates_and_positions(): void
    {
        $amina = $this->pupil($this->east, 'Amina', 'Nakato');
        $brian = $this->pupil($this->east, 'Brian', 'Okello');
        $cathy = $this->pupil($this->west, 'Cathy', 'Akello');
        $noMarks = $this->pupil($this->west, 'Daniel', 'Mugisha');

        // Amina: Mid 30/50 = 60%, End 80 → 0.3*60 + 0.7*80 = 74 → D2 (2 points)
        $this->marks($amina, [
            'English' => [30, 80], 'Mathematics' => [45, 95], 'Science' => [40, 82], 'Social Studies' => [35, 70], 'Religious Education' => [10, 20],
        ]);
        // Brian missed the mid-term in Maths: only End counts, not zero
        $this->marks($brian, [
            'English' => [25, 60], 'Mathematics' => [null, 90], 'Science' => [20, 40], 'Social Studies' => [30, 66],
        ]);
        $this->marks($cathy, [
            'English' => [30, 80], 'Mathematics' => [45, 95], 'Science' => [40, 82], 'Social Studies' => [35, 70],
        ]);

        $results = TermResults::forClass($this->term, $this->p7);
        $a = $results->students[$amina->getKey()];
        $b = $results->students[$brian->getKey()];
        $c = $results->students[$cathy->getKey()];

        $english = $a->subjects->first(fn ($s) => $s->subject->name === 'English');
        $this->assertSame(74.0, $english->final);
        $this->assertSame('D2', $english->grade);
        $this->assertSame(2.0, $english->points);

        $maths = $b->subjects->first(fn ($s) => $s->subject->name === 'Mathematics');
        $this->assertSame([$this->end->getKey() => 90.0], $maths->scores);
        $this->assertSame(90.0, $maths->final);

        // Aggregate: best 4 subjects that count (RE doesn't)
        // Amina: English 74 (D2=2), Maths 93.5 (D1=1), Science 81.4 (D1=1), SST 70 (D2=2) → 6
        $this->assertSame(6, $a->aggregate);
        $this->assertSame('Division I', $a->division);
        $this->assertSame('St Marys Primary', $this->school->name);

        // Amina and Cathy tie on aggregate 6; Amina's RE pulls her average down, so Cathy is first
        $this->assertSame(1, $c->position);
        $this->assertSame(2, $a->position);
        $this->assertSame(3, $b->position);
        $this->assertSame(3, $a->outOf);
        $this->assertNull($results->students[$noMarks->getKey()]->position);

        // Positions within streams
        $this->assertSame(1, $a->streamPosition);
        $this->assertSame(2, $a->streamOutOf);
        $this->assertSame(1, $c->streamPosition);
        $this->assertSame('P.7 East', $a->classLabel);
    }

    public function test_teachers_enter_marks_only_for_their_own_subjects_and_streams(): void
    {
        $teacher = $this->staff(StaffRole::Teacher, 'Peter Okello');
        $this->assign($teacher, 'Mathematics', 's:'.$this->east->getKey());
        $eastPupil = $this->pupil($this->east, 'Amina', 'Nakato');
        $westPupil = $this->pupil($this->west, 'Cathy', 'Akello');
        $this->signInAs($teacher);

        $page = Livewire::test(EnterMarks::class);

        // Only their subject is offered
        $this->assertSame([$this->subjects['Mathematics']->getKey() => 'Mathematics'], invade($page->instance())->scope()->markableSubjects());

        $page->set('selection.assessment_id', $this->end->getKey())
            ->set('selection.subject_id', $this->subjects['Mathematics']->getKey())
            ->set('selection.class_key', 's:'.$this->east->getKey())
            ->assertCanSeeTableRecords([$eastPupil])
            ->assertCanNotSeeTableRecords([$westPupil])
            ->call('updateTableColumnState', 'score', (string) $eastPupil->getKey(), '87.5');

        $this->assertSame(87.5, Mark::where('student_id', $eastPupil->getKey())->value('score'));
        $this->assertSame($teacher->getKey(), Mark::where('student_id', $eastPupil->getKey())->value('entered_by'));
        $this->assertSame($this->east->getKey(), Mark::where('student_id', $eastPupil->getKey())->value('stream_id'));

        // Over the maximum is refused
        $page->call('updateTableColumnState', 'score', (string) $eastPupil->getKey(), '120');
        $this->assertSame(87.5, Mark::where('student_id', $eastPupil->getKey())->value('score'));

        // Clearing the box removes the mark
        $page->call('updateTableColumnState', 'score', (string) $eastPupil->getKey(), '');
        $this->assertSame(0, Mark::count());

        // The West stream and other subjects can't be reached, even by typing the values in
        $page->set('selection.class_key', 's:'.$this->west->getKey())
            ->assertCanNotSeeTableRecords([$westPupil])
            ->call('updateTableColumnState', 'score', (string) $westPupil->getKey(), '50');
        $page->set('selection.subject_id', $this->subjects['English']->getKey())
            ->set('selection.class_key', 's:'.$this->east->getKey())
            ->assertCanNotSeeTableRecords([$eastPupil])
            ->call('updateTableColumnState', 'score', (string) $eastPupil->getKey(), '50');

        $this->assertSame(0, Mark::count());
    }

    public function test_head_teachers_can_enter_any_mark_and_bursars_cannot_open_marks(): void
    {
        $pupil = $this->pupil($this->west, 'Cathy', 'Akello');

        Livewire::test(EnterMarks::class)
            ->set('selection.assessment_id', $this->mid->getKey())
            ->set('selection.subject_id', $this->subjects['Science']->getKey())
            ->set('selection.class_key', 'c:'.$this->p7->getKey())
            ->call('updateTableColumnState', 'score', (string) $pupil->getKey(), '41');

        $this->assertSame(41.0, Mark::where('student_id', $pupil->getKey())->value('score'));

        $this->actingAs($this->staff(StaffRole::Bursar))->get('/school/stmarys/enter-marks')->assertForbidden();
        $this->actingAs($this->staff(StaffRole::Bursar))->get('/school/stmarys/report-cards')->assertForbidden();
        $this->actingAs($this->staff(StaffRole::Teacher))->get('/school/stmarys/exams')->assertForbidden();
        $this->actingAs($this->staff(StaffRole::Teacher))->get('/school/stmarys/grading-scales')->assertForbidden();
    }

    public function test_class_teachers_write_their_comments_and_only_head_teachers_publish(): void
    {
        $classTeacher = $this->staff(StaffRole::Teacher, 'Grace Class');
        $this->east->update(['class_teacher_id' => $classTeacher->getKey()]);
        $subjectTeacher = $this->staff(StaffRole::Teacher, 'Sam Subject');
        $this->assign($subjectTeacher, 'English', 'c:'.$this->p7->getKey());

        $amina = $this->pupil($this->east, 'Amina', 'Nakato');
        $cathy = $this->pupil($this->west, 'Cathy', 'Akello');
        $this->marks($amina, ['English' => [30, 80]]);
        $this->marks($cathy, ['English' => [20, 50]]);

        // The class teacher of East comments on East only, and can't publish
        $this->signInAs($classTeacher);
        $page = Livewire::test(ReportCards::class)
            ->set('selection.academic_term_id', $this->term->getKey())
            ->set('selection.class_key', 's:'.$this->east->getKey())
            ->assertCanSeeTableRecords([$amina])
            ->assertCanNotSeeTableRecords([$cathy])
            ->assertActionHidden('publish')
            ->callTableAction('comments', $amina, data: ['class_teacher_comment' => 'A hard-working pupil.', 'head_teacher_comment' => 'Sneaky'])
            ->assertHasNoTableActionErrors();

        $comment = ReportComment::where('student_id', $amina->getKey())->first();
        $this->assertSame('A hard-working pupil.', $comment->class_teacher_comment);
        $this->assertNull($comment->head_teacher_comment);

        // A subject teacher sees the class but can't comment
        $this->signInAs($subjectTeacher);
        Livewire::test(ReportCards::class)
            ->set('selection.academic_term_id', $this->term->getKey())
            ->set('selection.class_key', 'c:'.$this->p7->getKey())
            ->assertCanSeeTableRecords([$amina, $cathy])
            ->assertTableActionHidden('comments', $amina);

        // The head teacher writes the head teacher's comment and publishes
        $this->signInAs($this->head);
        Livewire::test(ReportCards::class)
            ->set('selection.academic_term_id', $this->term->getKey())
            ->set('selection.class_key', 'c:'.$this->p7->getKey())
            ->callTableAction('comments', $amina, data: ['class_teacher_comment' => 'A hard-working pupil.', 'head_teacher_comment' => 'Well done.'])
            ->callAction('suggestComments')
            ->callAction('publish')
            ->assertHasNoActionErrors();

        $this->assertSame('Well done.', $comment->refresh()->head_teacher_comment);
        $this->assertNotEmpty(ReportComment::where('student_id', $cathy->getKey())->value('class_teacher_comment'));
        $this->assertTrue(ResultPublication::isPublished($this->term->getKey(), $this->p7->getKey()));

        Livewire::test(ReportCards::class)
            ->set('selection.academic_term_id', $this->term->getKey())
            ->set('selection.class_key', 'c:'.$this->p7->getKey())
            ->callAction('unpublish');

        $this->assertFalse(ResultPublication::isPublished($this->term->getKey(), $this->p7->getKey()));
    }

    public function test_teachers_cannot_see_classes_they_dont_teach(): void
    {
        $other = SchoolClass::factory()->create(['school_id' => $this->school->getKey(), 'name' => 'P.6']);
        $p6pupil = Student::factory()->create(['school_id' => $this->school->getKey(), 'school_class_id' => $other->getKey()]);
        $this->marks($p6pupil, ['English' => [40, 90]]);
        $teacher = $this->staff(StaffRole::Teacher);
        $this->assign($teacher, 'English', 'c:'.$this->p7->getKey());
        $this->signInAs($teacher);

        Livewire::test(ReportCards::class)
            ->set('selection.academic_term_id', $this->term->getKey())
            ->set('selection.class_key', 'c:'.$other->getKey())
            ->assertCanNotSeeTableRecords([$p6pupil]);

        Filament::setCurrentPanel(null);
        $this->get(route('reports.print', ['term' => $this->term->getKey(), 'class' => $other->getKey()]))->assertNotFound();

        // The head teacher can print it
        $this->actingAs($this->head)->get(route('reports.print', ['term' => $this->term->getKey(), 'class' => $other->getKey()]))->assertOk()->assertSee($p6pupil->full_name);
    }

    public function test_report_cards_print_with_marks_comments_positions_and_fees(): void
    {
        $amina = $this->pupil($this->east, 'Amina', 'Nakato');
        $brian = $this->pupil($this->east, 'Brian', 'Okello');
        $this->marks($amina, ['English' => [30, 80], 'Mathematics' => [45, 95], 'Science' => [40, 82], 'Social Studies' => [35, 70]]);
        $this->marks($brian, ['English' => [25, 60]]);
        ReportComment::create(['school_id' => $this->school->getKey(), 'student_id' => $amina->getKey(), 'academic_term_id' => $this->term->getKey(), 'class_teacher_comment' => 'Excellent pupil.', 'head_teacher_comment' => 'Keep it up.']);
        $this->east->update(['class_teacher_id' => $this->staff(StaffRole::Teacher, 'Grace Class')->getKey()]);
        AcademicTerm::factory()->create(['school_id' => $this->school->getKey(), 'year' => 2027, 'term' => 1, 'is_current' => false, 'starts_on' => '2027-02-01']);

        Filament::setCurrentPanel(null);
        $url = route('reports.print', ['term' => $this->term->getKey(), 'class' => $this->p7->getKey(), 'student' => $amina->getKey()]);

        $this->get($url)
            ->assertOk()
            ->assertSee('TERM REPORT · TERM 3, 2026')
            ->assertSee('Nakato Amina')
            ->assertDontSee('Okello Brian')
            ->assertSeeInOrder(['English', '30', '80', '74', 'D2'])
            ->assertSeeInOrder(['Aggregate', '6', 'Division', 'Division I'])
            ->assertSeeInOrder(['Position in class', '1', 'of 2'])
            ->assertSee('Excellent pupil.')
            ->assertSee('Keep it up.')
            ->assertSee('Grace Class')
            ->assertSee('Sarah Head')
            ->assertSee('1 Feb 2027')
            ->assertSee('Fees balance');

        // The whole class prints in one go; positions and fees can be switched off
        $this->school->update(['show_positions' => false, 'report_shows_fees' => false]);
        $this->get(route('reports.print', ['term' => $this->term->getKey(), 'class' => $this->p7->getKey()]))
            ->assertOk()
            ->assertSee('Nakato Amina')
            ->assertSee('Okello Brian')
            ->assertDontSee('Position in class')
            ->assertDontSee('Fees balance');

        // Staff of another school, and parents' access to staff pages, are refused
        $this->actingAs($this->staff(StaffRole::HeadTeacher, school: School::factory()->create()))->get($url)->assertForbidden();
        auth()->logout();
        $this->get($url)->assertForbidden();
    }

    public function test_classes_get_a_grading_scale_and_class_teachers(): void
    {
        $teacher = $this->staff(StaffRole::Teacher, 'Grace Class');
        $outsider = User::factory()->create();

        $this->get("/school/stmarys/classes/{$this->p7->getKey()}/edit")->assertOk()->assertSee('Grading scale');

        Livewire::test(\App\Filament\School\Resources\SchoolClasses\Pages\CreateSchoolClass::class)
            ->fillForm(['name' => 'P.6', 'level' => 'primary', 'class_teacher_id' => $outsider->getKey()])
            ->call('create')
            ->assertHasFormErrors(['class_teacher_id']);

        Livewire::test(\App\Filament\School\Resources\SchoolClasses\Pages\CreateSchoolClass::class)
            ->fillForm(['name' => 'P.6', 'level' => 'primary', 'class_teacher_id' => $teacher->getKey(), 'grading_scale_id' => $this->p7->grading_scale_id])
            ->call('create')
            ->assertHasNoFormErrors();

        $p6 = SchoolClass::where('name', 'P.6')->first();
        $this->assertTrue($p6->classTeacher->is($teacher));
        $this->assertSame($this->p7->grading_scale_id, $p6->grading_scale_id);
    }
}
