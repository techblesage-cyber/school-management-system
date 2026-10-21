<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\StaffRole;
use App\Filament\School\Pages\TakeAttendance;
use App\Models\AcademicTerm;
use App\Models\AttendanceRecord;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Stream;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private SchoolClass $s1;

    private Stream $east;

    private Stream $west;

    private AcademicTerm $term;

    private User $classTeacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::factory()->create(['slug' => 'stmarys']);
        $this->term = AcademicTerm::factory()->create(['school_id' => $this->school->getKey()]);
        $this->s1 = SchoolClass::factory()->create(['school_id' => $this->school->getKey(), 'name' => 'S.1']);
        $this->east = $this->s1->streams()->create(['name' => 'East']);
        $this->west = $this->s1->streams()->create(['name' => 'West']);
        $this->classTeacher = $this->staff(StaffRole::Teacher);
        $this->east->update(['class_teacher_id' => $this->classTeacher->getKey()]);
        $this->signInAs($this->classTeacher);
    }

    private function staff(StaffRole $role): User
    {
        $user = User::factory()->create();
        $this->school->users()->attach($user, ['role' => $role->value]);

        return $user;
    }

    private function signInAs(User $user): void
    {
        $this->actingAs($user);
        Filament::setCurrentPanel('school');
        Filament::setTenant($this->school);
        Filament::bootCurrentPanel();
    }

    private function student(Stream $stream): Student
    {
        return Student::factory()->create(['school_id' => $this->school->getKey(), 'school_class_id' => $this->s1->getKey(), 'stream_id' => $stream->getKey()]);
    }

    private function page(string $classKey, ?string $date = null)
    {
        return Livewire::test(TakeAttendance::class)
            ->set('selection.date', $date ?? today()->toDateString())
            ->set('selection.class_key', $classKey);
    }

    public function test_a_class_teacher_takes_the_register_for_their_stream(): void
    {
        $amina = $this->student($this->east);
        $brian = $this->student($this->east);
        $cathy = $this->student($this->east);
        $westPupil = $this->student($this->west);

        $page = $this->page('s:'.$this->east->getKey())
            ->assertCanSeeTableRecords([$amina, $brian, $cathy])
            ->assertCanNotSeeTableRecords([$westPupil])
            ->call('updateTableColumnState', 'attendance', (string) $brian->getKey(), 'absent')
            ->call('updateTableColumnState', 'note', (string) $brian->getKey(), 'Sick')
            ->call('updateTableColumnState', 'attendance', (string) $cathy->getKey(), 'late')
            ->callAction('markRestPresent')
            ->assertNotified('Marked 1 student present');

        $this->assertSame(AttendanceStatus::Present, $amina->attendanceRecords()->first()->status);
        $this->assertSame('Sick', $brian->attendanceRecords()->first()->note);
        $this->assertSame($this->term->getKey(), $brian->attendanceRecords()->first()->academic_term_id);

        // Changing a mark updates the same day rather than adding another
        $page->call('updateTableColumnState', 'attendance', (string) $brian->getKey(), 'excused');
        $this->assertSame(1, $brian->attendanceRecords()->count());
        $this->assertSame(AttendanceStatus::Excused, $brian->attendanceRecords()->first()->status);
        $this->assertSame('Sick', $brian->attendanceRecords()->first()->note);

        // The term summary: late counts as at school
        $this->assertSame(['present' => 1, 'absent' => 0, 'late' => 0], AttendanceRecord::summaryFor($amina, $this->term));
        $this->assertSame(['present' => 1, 'absent' => 0, 'late' => 1], AttendanceRecord::summaryFor($cathy, $this->term));
        $this->assertSame(['present' => 0, 'absent' => 1, 'late' => 0], AttendanceRecord::summaryFor($brian, $this->term));
        $this->assertNull(AttendanceRecord::summaryFor($westPupil, $this->term));
    }

    public function test_teachers_only_mark_classes_they_teach_or_are_class_teacher_of(): void
    {
        $westPupil = $this->student($this->west);

        // Not their stream: nothing listed, and saving is refused
        $this->page('s:'.$this->west->getKey())
            ->assertCanNotSeeTableRecords([$westPupil])
            ->call('updateTableColumnState', 'attendance', (string) $westPupil->getKey(), 'present');
        $this->page('c:'.$this->s1->getKey())->assertCanNotSeeTableRecords([$westPupil]);

        $this->assertSame(0, AttendanceRecord::count());

        // A subject teacher of the whole class can
        $maths = Subject::factory()->create(['school_id' => $this->school->getKey()]);
        $subjectTeacher = $this->staff(StaffRole::Teacher);
        TeachingAssignment::syncFor($this->school, $subjectTeacher, [['subject_id' => $maths->getKey(), 'classes' => ['c:'.$this->s1->getKey()]]]);
        $this->signInAs($subjectTeacher);

        $this->page('c:'.$this->s1->getKey())
            ->assertCanSeeTableRecords([$westPupil])
            ->call('updateTableColumnState', 'attendance', (string) $westPupil->getKey(), 'present');

        $this->assertSame(1, AttendanceRecord::count());
    }

    public function test_future_days_cannot_be_marked_and_bursars_cannot_take_attendance(): void
    {
        $amina = $this->student($this->east);

        $this->page('s:'.$this->east->getKey(), today()->addDay()->toDateString())
            ->assertCanNotSeeTableRecords([$amina]);

        $this->actingAs($this->staff(StaffRole::Bursar))->get('/school/stmarys/attendance')->assertForbidden();
        $this->actingAs($this->staff(StaffRole::Secretary))->get('/school/stmarys/attendance')->assertForbidden();
        $this->actingAs($this->classTeacher)->get('/school/stmarys/attendance')->assertOk();
    }

    public function test_the_dashboard_and_student_page_show_attendance(): void
    {
        $amina = $this->student($this->east);
        $brian = $this->student($this->east);
        AttendanceRecord::record($amina, today()->toDateString(), AttendanceStatus::Present);
        AttendanceRecord::record($brian, today()->toDateString(), AttendanceStatus::Absent, 'Sick');

        $this->signInAs($this->staff(StaffRole::HeadTeacher));
        Livewire::test(\App\Filament\School\Widgets\SchoolOverview::class)
            ->assertSeeInOrder(['At school today', '1', '1 absent']);

        $this->get("/school/stmarys/students/{$brian->getKey()}/edit")->assertOk();
        Livewire::test(\App\Filament\School\Resources\Students\RelationManagers\AttendanceRelationManager::class, [
            'ownerRecord' => $brian,
            'pageClass' => \App\Filament\School\Resources\Students\Pages\EditStudent::class,
        ])->assertSee('Sick')->assertSee('0 days present, 1 absent');
    }
}
