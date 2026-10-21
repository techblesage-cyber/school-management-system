<?php

namespace Tests\Feature;

use App\Enums\StaffRole;
use App\Filament\School\Pages\Staff;
use App\Filament\School\Resources\AdmissionApplications\Pages\ListAdmissionApplications;
use App\Filament\School\Resources\SchoolClasses\Pages\ListSchoolClasses;
use App\Filament\School\Resources\Students\Pages\EditStudent;
use App\Filament\School\Resources\Students\Pages\ViewStudent;
use App\Filament\School\Resources\Students\RelationManagers\GuardiansRelationManager;
use App\Models\AdmissionApplication;
use App\Models\Guardian;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Filament\Facades\Filament;
use App\Notifications\StaffLogin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class RolesPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private Student $student;

    private Guardian $guardian;

    private AdmissionApplication $application;

    private SchoolClass $class;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::factory()->create(['slug' => 'stmarys']);
        $this->class = SchoolClass::factory()->create(['school_id' => $this->school->getKey(), 'name' => 'S.1']);
        $this->student = Student::factory()->create(['school_id' => $this->school->getKey(), 'school_class_id' => $this->class->getKey()]);
        $this->guardian = Guardian::factory()->create(['school_id' => $this->school->getKey()]);
        $this->student->guardians()->attach($this->guardian, ['relationship' => 'mother', 'is_primary' => true]);
        $this->application = AdmissionApplication::factory()->create(['school_id' => $this->school->getKey(), 'school_class_id' => $this->class->getKey()]);
    }

    private function staff(StaffRole $role, ?School $school = null): User
    {
        $user = User::factory()->create();
        ($school ?? $this->school)->users()->attach($user, ['role' => $role->value]);

        return $user;
    }

    /**
     * Signs in and opens the school portal, as a request would.
     */
    private function signInAs(User $user): void
    {
        $this->actingAs($user);
        Filament::setCurrentPanel('school');
        Filament::setTenant($this->school);
        Filament::bootCurrentPanel();
    }

    /**
     * Which portal pages a role can open (true) or is refused (false).
     *
     * @return array<string, bool>
     */
    private function pageAccess(User $user): array
    {
        $s = $this->student->getKey();
        $g = $this->guardian->getKey();
        $a = $this->application->getKey();
        $results = [];

        foreach ([
            'students list' => '/school/stmarys/students',
            'view student' => "/school/stmarys/students/{$s}",
            'add student' => '/school/stmarys/students/create',
            'edit student' => "/school/stmarys/students/{$s}/edit",
            'parents list' => '/school/stmarys/parents',
            'view parent' => "/school/stmarys/parents/{$g}",
            'edit parent' => "/school/stmarys/parents/{$g}/edit",
            'classes list' => '/school/stmarys/classes',
            'add class' => '/school/stmarys/classes/create',
            'admissions' => '/school/stmarys/admissions',
            'add application' => '/school/stmarys/admissions/create',
            'edit application' => "/school/stmarys/admissions/{$a}/edit",
            'staff' => '/school/stmarys/staff',
            'school profile' => '/school/stmarys/profile',
        ] as $page => $url) {
            $status = $this->actingAs($user)->get($url)->status();
            $this->assertContains($status, [200, 403, 404], "{$page} returned {$status}");
            $results[$page] = $status === 200;
        }

        return $results;
    }

    public function test_teachers_and_bursars_can_only_look_at_students_and_parents(): void
    {
        foreach ([StaffRole::Teacher, StaffRole::Bursar] as $role) {
            $this->assertSame([
                'students list' => true,
                'view student' => true,
                'add student' => false,
                'edit student' => false,
                'parents list' => true,
                'view parent' => true,
                'edit parent' => false,
                'classes list' => true,
                'add class' => false,
                'admissions' => false,
                'add application' => false,
                'edit application' => false,
                'staff' => false,
                'school profile' => false,
            ], $this->pageAccess($this->staff($role)), $role->getLabel());
        }
    }

    public function test_secretaries_record_students_and_applications_but_do_not_decide(): void
    {
        $secretary = $this->staff(StaffRole::Secretary);

        $this->assertSame([
            'students list' => true,
            'view student' => true,
            'add student' => true,
            'edit student' => true,
            'parents list' => true,
            'view parent' => true,
            'edit parent' => true,
            'classes list' => true,
            'add class' => false,
            'admissions' => true,
            'add application' => true,
            'edit application' => true,
            'staff' => false,
            'school profile' => false,
        ], $this->pageAccess($secretary));

        $this->signInAs($secretary);

        Livewire::test(ListAdmissionApplications::class)
            ->assertTableActionHidden('accept', $this->application)
            ->assertTableActionHidden('reject', $this->application)
            ->assertTableActionHidden('enrol', $this->application);

        Livewire::test(EditStudent::class, ['record' => $this->student->getKey()])
            ->assertActionHidden('delete');

        Livewire::test(ListSchoolClasses::class)
            ->assertActionHidden('addStandardClasses');
    }

    public function test_head_teachers_decide_admissions_and_manage_classes_but_not_staff(): void
    {
        $head = $this->staff(StaffRole::HeadTeacher);

        $access = $this->pageAccess($head);
        $this->assertTrue($access['add class']);
        $this->assertTrue($access['school profile']);
        $this->assertFalse($access['staff']);

        $this->signInAs($head);

        Livewire::test(ListAdmissionApplications::class)
            ->assertTableActionVisible('accept', $this->application)
            ->assertTableActionVisible('enrol', $this->application);

        Livewire::test(EditStudent::class, ['record' => $this->student->getKey()])
            ->assertActionVisible('delete');
    }

    public function test_parents_tab_is_read_only_for_view_only_roles(): void
    {
        $this->signInAs($this->staff(StaffRole::Teacher));

        Livewire::test(GuardiansRelationManager::class, ['ownerRecord' => $this->student, 'pageClass' => ViewStudent::class])
            ->assertCanSeeTableRecords([$this->guardian])
            ->assertTableActionHidden('create')
            ->assertTableActionHidden('attach')
            ->assertTableActionHidden('detach', $this->guardian);

        $this->signInAs($this->staff(StaffRole::Secretary));

        Livewire::test(GuardiansRelationManager::class, ['ownerRecord' => $this->student, 'pageClass' => EditStudent::class])
            ->assertTableActionVisible('create')
            ->assertTableActionVisible('attach');
    }

    public function test_administrators_have_full_access(): void
    {
        $this->assertNotContains(false, $this->pageAccess($this->staff(StaffRole::Admin)));
    }

    public function test_administrators_add_new_staff_and_existing_accounts(): void
    {
        $admin = $this->staff(StaffRole::Admin);
        $otherSchool = School::factory()->create();
        $sharedTeacher = $this->staff(StaffRole::Teacher, $otherSchool);
        $sharedTeacher->update(['password' => 'their-own-password']);
        $subject = Subject::factory()->create(['school_id' => $this->school->getKey()]);
        $this->signInAs($admin);
        Notification::fake();

        Livewire::test(Staff::class)
            ->assertCanSeeTableRecords([$admin])
            ->assertCanNotSeeTableRecords([$sharedTeacher])
            ->callTableAction('create', data: [
                'email' => 'New.Bursar@Example.com',
                'name' => 'New Bursar',
                'role' => StaffRole::Bursar->value,
            ])
            ->assertHasNoTableActionErrors()
            ->assertNotified('New Bursar added')
            ->callTableAction('create', data: [
                'email' => $sharedTeacher->email,
                'role' => StaffRole::Teacher->value,
                'teaching' => [['subject_id' => $subject->getKey(), 'classes' => ['c:'.$this->class->getKey()]]],
            ])
            ->assertHasNoTableActionErrors()
            ->callTableAction('create', data: [
                'email' => $sharedTeacher->email,
                'role' => StaffRole::Teacher->value,
            ])
            ->assertHasTableActionErrors(['email']);

        // The new person's first password is made by the system and emailed;
        // they choose their own when they first sign in
        $bursar = User::where('email', 'new.bursar@example.com')->firstOrFail();
        $this->assertSame(StaffRole::Bursar, $bursar->roleAt($this->school));
        $this->assertFalse($bursar->isSuperAdmin());
        $this->assertTrue($bursar->must_change_password);
        $sentPassword = null;
        Notification::assertSentTo($bursar, StaffLogin::class, function (StaffLogin $mail) use ($bursar, &$sentPassword): bool {
            $sentPassword = $mail->password;
            $text = implode(' ', $mail->toMail($bursar)->introLines);

            return $mail->school->is($this->school) && str_contains($text, 'as Bursar') && str_contains($text, (string) $mail->password);
        });
        $this->assertTrue(Hash::check($sentPassword, $bursar->password));

        // The existing account keeps its password and its other school, and is told by email
        $sharedTeacher->refresh();
        Notification::assertSentTo($sharedTeacher, StaffLogin::class, fn (StaffLogin $mail): bool => $mail->password === null);
        $this->assertFalse($sharedTeacher->must_change_password);
        $this->assertTrue(Hash::check('their-own-password', $sharedTeacher->password));
        $this->assertSame(StaffRole::Teacher, $sharedTeacher->roleAt($this->school));
        $this->assertSame(StaffRole::Teacher, $sharedTeacher->roleAt($otherSchool));
    }

    public function test_administrators_cannot_take_over_shared_accounts_or_lock_themselves_out(): void
    {
        $admin = $this->staff(StaffRole::Admin);
        $ownTeacher = $this->staff(StaffRole::Teacher);
        $sharedTeacher = $this->staff(StaffRole::Teacher);
        School::factory()->create()->users()->attach($sharedTeacher, ['role' => StaffRole::Teacher->value]);
        $this->signInAs($admin);

        $page = Livewire::test(Staff::class);

        // Their own staff: role and name can be changed, and a new login sent
        $page->callTableAction('edit', $ownTeacher, data: [
            'name' => 'Renamed',
            'email' => $ownTeacher->email,
            'role' => StaffRole::Secretary->value,
        ])->assertHasNoTableActionErrors();

        $ownTeacher->refresh()->forgetSchoolRoles();
        $this->assertSame('Renamed', $ownTeacher->name);
        $this->assertSame(StaffRole::Secretary, $ownTeacher->roleAt($this->school));

        Notification::fake();
        $oldOwnPassword = $ownTeacher->password;
        $page->callTableAction('sendLogin', $ownTeacher)->assertNotified('New login for Renamed');
        $this->assertNotSame($oldOwnPassword, $ownTeacher->refresh()->password);
        $this->assertTrue($ownTeacher->must_change_password);
        Notification::assertSentTo($ownTeacher, StaffLogin::class, fn (StaffLogin $mail): bool => $mail->isNewPassword && Hash::check($mail->password, $ownTeacher->password));

        // Someone who also works elsewhere: only the role here changes, and
        // this school can't send them a new password
        $oldPassword = $sharedTeacher->password;
        $page->callTableAction('edit', $sharedTeacher, data: [
            'name' => 'Hijacked',
            'email' => 'attacker@example.com',
            'password' => 'attacker-pass',
            'role' => StaffRole::Bursar->value,
        ])->assertHasNoTableActionErrors()
            ->assertTableActionHidden('sendLogin', $sharedTeacher)
            ->assertTableActionHidden('sendLogin', $admin);

        $sharedTeacher->refresh()->forgetSchoolRoles();
        $this->assertNotSame('Hijacked', $sharedTeacher->name);
        $this->assertNotSame('attacker@example.com', $sharedTeacher->email);
        $this->assertSame($oldPassword, $sharedTeacher->password);
        $this->assertSame(StaffRole::Bursar, $sharedTeacher->roleAt($this->school));

        // Administrators can't remove or demote themselves
        $page->assertTableActionHidden('detach', $admin)
            ->assertTableActionVisible('detach', $ownTeacher);

        $page->callTableAction('edit', $admin, data: ['role' => StaffRole::Teacher->value]);
        $this->assertSame(StaffRole::Admin, $admin->forgetSchoolRoles()->roleAt($this->school));
    }

    public function test_super_admins_can_do_everything_in_any_school(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->assertNotContains(false, $this->pageAccess($superAdmin));
    }
}
