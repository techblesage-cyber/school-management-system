<?php

namespace Tests\Feature;

use App\Enums\StaffRole;
use App\Filament\School\Pages\Staff;
use App\Filament\School\Resources\Guardians\Pages\EditGuardian;
use App\Filament\School\Resources\Students\Pages\CreateStudent;
use App\Filament\School\Resources\Students\Pages\EditStudent;
use App\Filament\School\Resources\Students\RelationManagers\GuardiansRelationManager;
use App\Filament\School\Resources\Subjects\Pages\ListSubjects;
use App\Filament\School\Widgets\MyTeaching;
use App\Models\Guardian;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class StaffPhotosTeachingTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $admin;

    private SchoolClass $s1;

    private SchoolClass $s2;

    private Subject $maths;

    private Subject $physics;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Repeater::fake();

        $this->school = School::factory()->create(['slug' => 'stmarys']);
        $this->admin = User::factory()->create();
        $this->school->users()->attach($this->admin, ['role' => StaffRole::Admin->value]);

        $this->s1 = SchoolClass::factory()->create(['school_id' => $this->school->getKey(), 'name' => 'S.1']);
        $this->s1->streams()->createMany([['name' => 'East'], ['name' => 'West']]);
        $this->s2 = SchoolClass::factory()->create(['school_id' => $this->school->getKey(), 'name' => 'S.2']);
        $this->maths = Subject::factory()->create(['school_id' => $this->school->getKey(), 'name' => 'Mathematics']);
        $this->physics = Subject::factory()->create(['school_id' => $this->school->getKey(), 'name' => 'Physics']);

        $this->actingAs($this->admin);
        Filament::setCurrentPanel('school');
        Filament::setTenant($this->school);
        Filament::bootCurrentPanel();
    }

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->image('passport.jpg', 420, 540);
    }

    private function streamKey(string $name): string
    {
        return 's:'.$this->s1->streams()->where('name', $name)->value('id');
    }

    public function test_a_teacher_is_added_with_a_photo_and_several_subjects_and_classes(): void
    {
        Livewire::test(Staff::class)
            ->callTableAction('create', data: [
                'email' => 'okello@example.com',
                'name' => 'Peter Okello',
                'password' => 'teacher-pass',
                'photo' => $this->photo(),
                'role' => StaffRole::Teacher->value,
                'teaching' => [
                    ['subject_id' => $this->maths->getKey(), 'classes' => ['c:'.$this->s1->getKey(), 'c:'.$this->s2->getKey()]],
                    ['subject_id' => $this->physics->getKey(), 'classes' => [$this->streamKey('East')]],
                ],
            ])
            ->assertHasNoTableActionErrors();

        $teacher = User::where('email', 'okello@example.com')->firstOrFail();

        $this->assertNotNull($teacher->photo);
        Storage::disk('public')->assertExists($teacher->photo);
        $this->assertStringStartsWith('staff-photos/', $teacher->photo);

        $this->assertSame(
            ['Mathematics: S.1, S.2', 'Physics: S.1 East'],
            Staff::teachingSummary($teacher->teachingAssignments()->with(['subject', 'schoolClass', 'stream'])->get()),
        );
    }

    public function test_teachers_must_have_at_least_one_subject_but_other_roles_need_not(): void
    {
        Livewire::test(Staff::class)->callTableAction('create', data: [
            'email' => 'noclass@example.com',
            'name' => 'No Subjects',
            'password' => 'teacher-pass',
            'role' => StaffRole::Teacher->value,
            'teaching' => [],
        ])->assertHasTableActionErrors(['teaching']);

        Livewire::test(Staff::class)->callTableAction('create', data: [
            'email' => 'bursar@example.com',
            'name' => 'The Bursar',
            'password' => 'bursar-pass',
            'role' => StaffRole::Bursar->value,
            'teaching' => [],
        ])->assertHasNoTableActionErrors();

        $this->assertFalse(User::where('email', 'noclass@example.com')->exists());
    }

    public function test_choosing_a_whole_class_covers_its_streams_and_other_schools_classes_are_ignored(): void
    {
        $other = School::factory()->create();
        Filament::setCurrentPanel(null);
        $foreignClass = SchoolClass::factory()->create(['school_id' => $other->getKey(), 'name' => 'Foreign']);
        $foreignSubject = Subject::factory()->create(['school_id' => $other->getKey(), 'name' => 'Foreign subject']);
        Filament::setCurrentPanel('school');

        $teacher = User::factory()->create();
        $this->school->users()->attach($teacher, ['role' => StaffRole::Teacher->value]);

        TeachingAssignment::syncFor($this->school, $teacher, [
            ['subject_id' => $this->maths->getKey(), 'classes' => ['c:'.$this->s1->getKey(), $this->streamKey('East'), 'c:'.$foreignClass->getKey()]],
            ['subject_id' => $foreignSubject->getKey(), 'classes' => ['c:'.$this->s2->getKey()]],
        ]);

        $rows = TeachingAssignment::where('user_id', $teacher->getKey())->get();
        $this->assertCount(1, $rows);
        $this->assertSame($this->s1->getKey(), $rows->first()->school_class_id);
        $this->assertNull($rows->first()->stream_id);
    }

    public function test_editing_a_teacher_replaces_their_subjects_and_removing_them_clears_teaching(): void
    {
        $teacher = User::factory()->create();
        $this->school->users()->attach($teacher, ['role' => StaffRole::Teacher->value]);
        TeachingAssignment::syncFor($this->school, $teacher, [
            ['subject_id' => $this->maths->getKey(), 'classes' => ['c:'.$this->s1->getKey()]],
        ]);

        Livewire::test(Staff::class)
            ->mountTableAction('edit', $teacher)
            ->assertTableActionDataSet([
                'teaching' => [['subject_id' => $this->maths->getKey(), 'classes' => ['c:'.$this->s1->getKey()]]],
            ])
            ->setTableActionData([
                'teaching' => [['subject_id' => $this->physics->getKey(), 'classes' => ['c:'.$this->s2->getKey(), $this->streamKey('West')]]],
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors()
            ->assertSee('Physics: S.1 West, S.2');

        $this->assertSame(
            ['Physics: S.1 West, S.2'],
            Staff::teachingSummary($teacher->teachingAssignments()->with(['subject', 'schoolClass', 'stream'])->get()),
        );

        Livewire::test(Staff::class)->callTableAction('detach', $teacher);

        $this->assertSame(0, $teacher->teachingAssignments()->count());
        $this->assertNull($teacher->forgetSchoolRoles()->roleAt($this->school));
    }

    public function test_teachers_see_their_own_subjects_on_the_dashboard(): void
    {
        $teacher = User::factory()->create();
        $this->school->users()->attach($teacher, ['role' => StaffRole::Teacher->value]);
        TeachingAssignment::syncFor($this->school, $teacher, [
            ['subject_id' => $this->physics->getKey(), 'classes' => [$this->streamKey('East')]],
        ]);

        $this->actingAs($teacher);

        $this->assertTrue(MyTeaching::canView());
        Livewire::test(MyTeaching::class)->assertSee('Physics')->assertSee('S.1 East');

        $this->actingAs($this->admin);
        $this->assertFalse(MyTeaching::canView());
    }

    public function test_standard_subjects_are_added_once(): void
    {
        Livewire::test(ListSubjects::class)
            ->callAction('addStandardSubjects', data: ['levels' => ['o_level', 'a_level']])
            ->assertHasNoActionErrors();

        $names = $this->school->subjects()->pluck('name');
        $this->assertContains('Biology', $names);
        $this->assertContains('General Paper', $names);
        $this->assertSame($names->count(), $names->unique()->count(), 'no duplicates, e.g. Mathematics in both levels');
        $this->assertSame(1, $names->filter(fn ($name) => $name === 'Mathematics')->count());
    }

    public function test_students_and_parents_get_passport_photos_and_old_photos_are_removed(): void
    {
        Livewire::test(CreateStudent::class)
            ->fillForm([
                'last_name' => 'Nakato',
                'first_name' => 'Sarah',
                'gender' => 'female',
                'school_class_id' => $this->s2->getKey(),
                'residence' => 'day',
                'photo' => $this->photo(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $student = Student::where('first_name', 'Sarah')->firstOrFail();
        Storage::disk('public')->assertExists($student->photo);
        $firstPhoto = $student->photo;

        // Replacing a photo removes the old file from the server
        $newPhoto = $this->photo()->store('student-photos', 'public');
        $student->update(['photo' => $newPhoto]);

        $this->assertNotSame($firstPhoto, $student->photo);
        Storage::disk('public')->assertExists($student->photo);
        Storage::disk('public')->assertMissing($firstPhoto);

        Livewire::test(GuardiansRelationManager::class, ['ownerRecord' => $student, 'pageClass' => EditStudent::class])
            ->callTableAction('create', data: [
                'name' => 'Agnes Nakato',
                'phone' => '0772000111',
                'photo' => $this->photo(),
                'relationship' => 'mother',
                'is_primary' => true,
            ])
            ->assertHasNoTableActionErrors();

        $mother = Guardian::where('name', 'Agnes Nakato')->firstOrFail();
        $this->assertStringStartsWith('parent-photos/', $mother->photo);
        Storage::disk('public')->assertExists($mother->photo);

        $this->get("/school/stmarys/parents/{$mother->getKey()}/edit")->assertOk();
        Livewire::test(EditGuardian::class, ['record' => $mother->getKey()])->assertOk();

        $photo = $mother->photo;
        $mother->delete();
        Storage::disk('public')->assertMissing($photo);
    }
}
