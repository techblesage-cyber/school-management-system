<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\ClassLevel;
use App\Enums\GuardianRelationship;
use App\Enums\Residence;
use App\Enums\StaffRole;
use App\Filament\School\Resources\AdmissionApplications\Pages\CreateAdmissionApplication;
use App\Filament\School\Resources\AdmissionApplications\Pages\ListAdmissionApplications;
use App\Filament\School\Resources\Guardians\Pages\EditGuardian;
use App\Filament\School\Resources\Guardians\Pages\ListGuardians;
use App\Filament\School\Resources\Guardians\RelationManagers\StudentsRelationManager;
use App\Filament\School\Resources\SchoolClasses\Pages\CreateSchoolClass;
use App\Filament\School\Resources\SchoolClasses\Pages\ListSchoolClasses;
use App\Filament\School\Resources\Students\Pages\CreateStudent;
use App\Filament\School\Resources\Students\Pages\EditStudent;
use App\Filament\School\Resources\Students\Pages\ListStudents;
use App\Filament\School\Resources\Students\RelationManagers\GuardiansRelationManager;
use App\Filament\School\Widgets\SchoolOverview;
use App\Models\AdmissionApplication;
use App\Models\Guardian;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StudentsAdmissionsTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private School $otherSchool;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::factory()->create(['name' => 'St Marys', 'slug' => 'stmarys']);
        $this->otherSchool = School::factory()->create(['slug' => 'kings']);
        $this->staff = User::factory()->create();
        $this->school->users()->attach($this->staff, ['role' => StaffRole::HeadTeacher->value]);

        $this->actingAs($this->staff);
        Filament::setCurrentPanel('school');
        Filament::setTenant($this->school);
        Filament::bootCurrentPanel();
    }

    /**
     * Creates records for another school. Inside the portal, Filament files
     * every new record under the current school, so step outside it first.
     */
    private function forOtherSchool(callable $create): mixed
    {
        Filament::setCurrentPanel(null);

        try {
            return $create();
        } finally {
            Filament::setCurrentPanel('school');
        }
    }

    private function classWithStreams(string $name, array $streams = [], ?School $school = null): SchoolClass
    {
        $make = function () use ($name, $streams, $school): SchoolClass {
            $class = SchoolClass::factory()->create(['school_id' => ($school ?? $this->school)->getKey(), 'name' => $name]);

            foreach ($streams as $stream) {
                $class->streams()->create(['name' => $stream]);
            }

            return $class;
        };

        return $school ? $this->forOtherSchool($make) : $make();
    }

    public function test_all_school_portal_pages_load(): void
    {
        $student = Student::factory()->create(['school_id' => $this->school->getKey()]);
        $guardian = Guardian::factory()->create(['school_id' => $this->school->getKey()]);
        $application = AdmissionApplication::factory()->create(['school_id' => $this->school->getKey()]);

        foreach ([
            '/school/stmarys',
            '/school/stmarys/classes',
            '/school/stmarys/classes/create',
            '/school/stmarys/students',
            '/school/stmarys/students/create',
            "/school/stmarys/students/{$student->getKey()}/edit",
            '/school/stmarys/parents',
            "/school/stmarys/parents/{$guardian->getKey()}/edit",
            '/school/stmarys/admissions',
            '/school/stmarys/admissions/create',
            "/school/stmarys/admissions/{$application->getKey()}/edit",
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_records_of_another_school_cannot_be_seen_or_opened(): void
    {
        $mine = Student::factory()->create(['school_id' => $this->school->getKey()]);
        $theirs = $this->forOtherSchool(fn () => Student::factory()->create(['school_id' => $this->otherSchool->getKey()]));
        $theirParent = $this->forOtherSchool(fn () => Guardian::factory()->create(['school_id' => $this->otherSchool->getKey()]));
        $theirApplication = $this->forOtherSchool(fn () => AdmissionApplication::factory()->create(['school_id' => $this->otherSchool->getKey()]));

        Livewire::test(ListStudents::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);

        $this->get("/school/stmarys/students/{$theirs->getKey()}/edit")->assertNotFound();
        $this->get("/school/stmarys/parents/{$theirParent->getKey()}/edit")->assertNotFound();
        $this->get("/school/stmarys/admissions/{$theirApplication->getKey()}/edit")->assertNotFound();
    }

    public function test_standard_classes_are_added_once(): void
    {
        $this->classWithStreams('S.1');

        Livewire::test(ListSchoolClasses::class)
            ->callAction('addStandardClasses', data: ['levels' => [ClassLevel::OLevel->value, ClassLevel::ALevel->value]])
            ->assertHasNoActionErrors()
            ->callAction('addStandardClasses', data: ['levels' => [ClassLevel::OLevel->value]]);

        $this->assertSame(
            ['S.1', 'S.2', 'S.3', 'S.4', 'S.5', 'S.6'],
            $this->school->schoolClasses()->orderBy('name')->pluck('name')->all(),
        );
        $this->assertSame(0, $this->otherSchool->schoolClasses()->count());
    }

    public function test_a_class_is_created_with_its_streams(): void
    {
        Livewire::test(CreateSchoolClass::class)
            ->fillForm(['name' => 'P.5', 'level' => ClassLevel::Primary->value, 'streams' => [['name' => 'East'], ['name' => 'West']]])
            ->call('create')
            ->assertHasNoErrors();

        $class = $this->school->schoolClasses()->where('name', 'P.5')->firstOrFail();
        $this->assertSame(['East', 'West'], $class->streams()->pluck('name')->all());

        Student::factory()->count(2)->create(['school_id' => $this->school->getKey(), 'school_class_id' => $class->getKey()]);
        Student::factory()->create(['school_id' => $this->school->getKey(), 'school_class_id' => $class->getKey(), 'status' => 'left']);

        Livewire::test(ListSchoolClasses::class)
            ->assertCanSeeTableRecords([$class])
            ->assertTableColumnStateSet('active_students_count', 2, $class)
            ->assertTableActionHidden('delete', $class);

        // The same name is fine in another school, but not twice in this one
        $this->classWithStreams('P.6', school: $this->otherSchool);

        Livewire::test(CreateSchoolClass::class)
            ->fillForm(['name' => 'P.5', 'level' => ClassLevel::Primary->value])
            ->call('create')
            ->assertHasFormErrors(['name']);

        Livewire::test(CreateSchoolClass::class)
            ->fillForm(['name' => 'P.6', 'level' => ClassLevel::Primary->value])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    public function test_students_get_yearly_admission_numbers_per_school(): void
    {
        $class = $this->classWithStreams('S.1', ['East', 'West']);
        $east = $class->streams()->where('name', 'East')->first();

        Livewire::test(CreateStudent::class)
            ->fillForm([
                'last_name' => 'Okello',
                'first_name' => 'Brian',
                'gender' => 'male',
                'school_class_id' => $class->getKey(),
                'stream_id' => $east->getKey(),
                'residence' => Residence::Boarding->value,
                'admission_date' => '2026-02-03',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $brian = Student::where('first_name', 'Brian')->firstOrFail();
        $this->assertSame('2026/0001', $brian->admission_number);
        $this->assertSame($this->school->getKey(), $brian->school_id);
        $this->assertSame('S.1 East', $brian->class_and_stream);

        $second = Student::factory()->create(['school_id' => $this->school->getKey(), 'admission_date' => '2026-05-01']);
        $otherSchools = $this->forOtherSchool(fn () => Student::factory()->create(['school_id' => $this->otherSchool->getKey(), 'admission_date' => '2026-05-01']));
        $nextYear = Student::factory()->create(['school_id' => $this->school->getKey(), 'admission_date' => '2027-01-10']);

        $this->assertSame('2026/0002', $second->admission_number);
        $this->assertSame('2026/0001', $otherSchools->admission_number);
        $this->assertSame('2027/0001', $nextYear->admission_number);

        // A number already used in this school is refused
        Livewire::test(CreateStudent::class)
            ->fillForm([
                'admission_number' => '2026/0001',
                'last_name' => 'Nakato',
                'first_name' => 'Sarah',
                'gender' => 'female',
                'school_class_id' => $class->getKey(),
                'residence' => Residence::Day->value,
            ])
            ->call('create')
            ->assertHasFormErrors(['admission_number']);
    }

    public function test_students_cannot_be_put_in_another_schools_class_or_stream(): void
    {
        $mine = $this->classWithStreams('S.1', ['East']);
        $theirs = $this->classWithStreams('S.1', ['A'], $this->otherSchool);

        Livewire::test(CreateStudent::class)
            ->fillForm(['last_name' => 'X', 'first_name' => 'Y', 'gender' => 'male', 'school_class_id' => $theirs->getKey()])
            ->call('create')
            ->assertHasFormErrors(['school_class_id']);

        Livewire::test(CreateStudent::class)
            ->fillForm([
                'last_name' => 'X', 'first_name' => 'Y', 'gender' => 'male',
                'school_class_id' => $mine->getKey(),
                'stream_id' => $theirs->streams()->first()->getKey(),
            ])
            ->call('create')
            ->assertHasFormErrors(['stream_id']);

        $this->assertSame(0, Student::count());
    }

    public function test_parents_are_added_and_linked_to_students(): void
    {
        $student = Student::factory()->create(['school_id' => $this->school->getKey()]);
        $sibling = Student::factory()->create(['school_id' => $this->school->getKey()]);
        $otherParent = $this->forOtherSchool(fn () => Guardian::factory()->create(['school_id' => $this->otherSchool->getKey()]));

        Livewire::test(GuardiansRelationManager::class, ['ownerRecord' => $student, 'pageClass' => EditStudent::class])
            ->callTableAction('create', data: [
                'name' => 'Grace Achieng',
                'phone' => '0772 123 456',
                'relationship' => GuardianRelationship::Mother->value,
                'is_primary' => true,
            ])
            ->assertHasNoTableActionErrors();

        $grace = Guardian::where('name', 'Grace Achieng')->firstOrFail();
        $this->assertSame('0772123456', $grace->phone);
        $this->assertSame($this->school->getKey(), $grace->school_id);
        $this->assertSame('mother', $student->guardians()->first()->pivot->relationship);

        // Link the same mother to a sibling from her page
        Livewire::test(StudentsRelationManager::class, ['ownerRecord' => $grace, 'pageClass' => EditGuardian::class])
            ->callTableAction('attach', data: [
                'recordId' => $sibling->getKey(),
                'relationship' => GuardianRelationship::Mother->value,
                'is_primary' => true,
            ])
            ->assertHasNoTableActionErrors()
            ->assertCanSeeTableRecords([$student, $sibling]);

        Livewire::test(ListGuardians::class)
            ->assertCanSeeTableRecords([$grace])
            ->assertCanNotSeeTableRecords([$otherParent]);
    }

    public function test_applications_get_references_and_can_be_declined(): void
    {
        $class = $this->classWithStreams('S.1');

        Livewire::test(CreateAdmissionApplication::class)
            ->fillForm([
                'last_name' => 'Mugisha',
                'first_name' => 'Daniel',
                'gender' => 'male',
                'school_class_id' => $class->getKey(),
                'guardian_name' => 'Robert Mugisha',
                'guardian_phone' => '0701 000111',
                'guardian_relationship' => GuardianRelationship::Father->value,
                'applied_on' => '2026-10-01',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $application = AdmissionApplication::firstOrFail();
        $this->assertSame('APP-2026-0001', $application->reference);
        $this->assertSame(ApplicationStatus::Pending, $application->status);

        Livewire::test(ListAdmissionApplications::class)
            ->callTableAction('reject', $application)
            ->assertHasNoTableActionErrors()
            ->assertTableActionHidden('enrol', $application->refresh());

        $this->assertSame(ApplicationStatus::Rejected, $application->status);
    }

    public function test_enrolling_an_application_creates_the_student_and_reuses_the_parent(): void
    {
        $class = $this->classWithStreams('S.1', ['East']);
        $east = $class->streams()->first();
        $olderChild = Student::factory()->create(['school_id' => $this->school->getKey()]);
        $father = Guardian::factory()->create(['school_id' => $this->school->getKey(), 'name' => 'Robert Mugisha', 'phone' => '0701000111']);
        $olderChild->guardians()->attach($father, ['relationship' => 'father', 'is_primary' => true]);

        $application = AdmissionApplication::factory()->create([
            'school_id' => $this->school->getKey(),
            'first_name' => 'Daniel',
            'last_name' => 'Mugisha',
            'school_class_id' => $class->getKey(),
            'guardian_name' => 'Robert Mugisha',
            'guardian_phone' => '+256 701 000 111',
            'guardian_relationship' => GuardianRelationship::Father,
        ]);

        Livewire::test(ListAdmissionApplications::class)
            ->callTableAction('accept', $application)
            ->callTableAction('enrol', $application, data: [
                'school_class_id' => $class->getKey(),
                'stream_id' => $east->getKey(),
                'residence' => Residence::Boarding->value,
                'admission_date' => now()->toDateString(),
            ])
            ->assertHasNoTableActionErrors();

        $application->refresh();
        $daniel = $application->student;

        $this->assertSame(ApplicationStatus::Enrolled, $application->status);
        $this->assertSame('Daniel', $daniel->first_name);
        $this->assertSame('S.1 East', $daniel->class_and_stream);
        $this->assertSame(Residence::Boarding, $daniel->residence);
        $this->assertSame(now()->year.'/0002', $daniel->admission_number);

        // The father was found by phone number, not added a second time
        $this->assertSame(1, Guardian::count());
        $this->assertTrue($daniel->guardians()->first()->is($father));
        $this->assertCount(2, $father->students);

        // An enrolled application can't be enrolled again
        $this->expectException(\LogicException::class);
        $application->enrol(['school_class_id' => $class->getKey()]);
    }

    public function test_dashboard_shows_this_schools_numbers_only(): void
    {
        Student::factory()->count(3)->create(['school_id' => $this->school->getKey(), 'gender' => 'female']);
        Student::factory()->create(['school_id' => $this->school->getKey(), 'gender' => 'male', 'residence' => 'boarding']);
        $this->forOtherSchool(fn () => Student::factory()->count(5)->create(['school_id' => $this->otherSchool->getKey()]));
        AdmissionApplication::factory()->count(2)->create(['school_id' => $this->school->getKey()]);

        $this->get('/school/stmarys')->assertOk();

        Livewire::test(SchoolOverview::class)
            ->assertSeeInOrder(['Students', '4', '1 boy · 3 girls'])
            ->assertSeeInOrder(['Boarders', '1', '3 day scholars'])
            ->assertSeeInOrder(['Pending applications', '2']);
    }
}
