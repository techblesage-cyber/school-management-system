<?php

namespace Tests\Feature;

use App\Enums\GuardianRelationship;
use App\Enums\StaffRole;
use App\Filament\Admin\Resources\Schools\Pages\EditSchool;
use App\Filament\Admin\Resources\Schools\RelationManagers\UsersRelationManager;
use App\Filament\School\Resources\AdmissionApplications\Pages\ListAdmissionApplications;
use App\Filament\School\Resources\Guardians\Pages\CreateGuardian;
use App\Filament\Shared\ChoosePassword;
use App\Models\AdmissionApplication;
use App\Models\Guardian;
use App\Models\ParentAccount;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SmsMessage;
use App\Models\User;
use App\Notifications\ParentLogin;
use App\Notifications\StaffLogin;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class LoginsTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::factory()->create(['slug' => 'stmarys', 'name' => 'St Marys College']);
        Notification::fake();
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

    public function test_staff_with_a_password_from_the_system_choose_their_own_first(): void
    {
        $teacher = $this->staff(StaffRole::Teacher);
        $teacher->forceFill(['password' => 'kx7m-p2q9', 'must_change_password' => true])->save();

        $this->actingAs($teacher)->get('/school/stmarys')->assertRedirect('/school/choose-password');
        $this->get('/school/stmarys/attendance')->assertRedirect('/school/choose-password');
        $this->get('/school/choose-password')->assertOk()->assertSee('Choose your own password');

        Filament::setCurrentPanel('school');
        Livewire::test(ChoosePassword::class)
            ->fillForm(['password' => 'kx7m-p2q9', 'passwordConfirmation' => 'kx7m-p2q9'])
            ->call('save')
            ->assertHasFormErrors(['password'])
            ->fillForm(['password' => 'my-own-pass', 'passwordConfirmation' => 'typo-pass'])
            ->call('save')
            ->assertHasFormErrors(['password'])
            ->fillForm(['password' => 'my-own-pass', 'passwordConfirmation' => 'my-own-pass'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $teacher->refresh();
        $this->assertFalse($teacher->must_change_password);
        $this->assertTrue(Hash::check('my-own-pass', $teacher->password));
        $this->get('/school/stmarys')->assertOk();
    }

    public function test_a_new_parent_gets_their_login_by_email_and_sms_and_a_slip_to_print(): void
    {
        $this->school->update(['sms_enabled' => true]);
        $secretary = $this->staff(StaffRole::Secretary);
        $this->signInAs($secretary);

        Livewire::test(CreateGuardian::class)
            ->fillForm(['name' => 'Grace Nakato', 'phone' => '0772 123 456', 'email' => 'grace@example.com'])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified("Parents' portal login for Grace Nakato");

        $guardian = Guardian::query()->withoutGlobalScopes()->where('name', 'Grace Nakato')->sole();
        $account = $guardian->parentAccount;
        $this->assertSame($this->school->getKey(), $account->school_id);
        $this->assertTrue($account->must_change_password);

        $password = null;
        Notification::assertSentTo($account, ParentLogin::class, function (ParentLogin $mail) use (&$password, $account): bool {
            $password = $mail->password;

            return $mail->school->is($this->school) && str_contains(implode(' ', $mail->toMail($account)->introLines), '0772 123 456');
        });
        $this->assertTrue(Hash::check($password, $account->password));
        $this->assertStringContainsString($password, SmsMessage::where('purpose', 'portal_login')->sole()->message);

        // The slip, for the person who just made the login
        Filament::setCurrentPanel(null);
        $this->get(route('parents.login-slip', $guardian))->assertOk()->assertSee($password)->assertSee('0772 123 456')->assertSee('St Marys College');

        // Nobody else can print it
        $this->actingAs($this->staff(StaffRole::Secretary))->flushSession();
        $this->get(route('parents.login-slip', $guardian))->assertNotFound();
        $this->actingAs($this->staff(StaffRole::Teacher))->get(route('parents.login-slip', $guardian))->assertForbidden();
    }

    public function test_a_parent_without_email_or_sms_is_told_to_print_the_slip(): void
    {
        $this->signInAs($this->staff(StaffRole::Secretary));

        Livewire::test(CreateGuardian::class)
            ->fillForm(['name' => 'Robert Okello', 'phone' => '0701 000 111'])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $this->assertSame(1, ParentAccount::count());
        Notification::assertNothingSent();
        $this->assertSame(0, SmsMessage::count());

        // A teacher adding a parent doesn't hand out logins
        $this->signInAs($this->staff(StaffRole::Teacher));
        $this->assertFalse(Filament::auth()->user()->canInSchool(\App\Enums\SchoolAbility::ManageParentAccess, $this->school));
    }

    public function test_enrolling_a_new_student_gives_their_parent_a_login(): void
    {
        $this->signInAs($this->staff(StaffRole::Admin));
        $class = SchoolClass::factory()->create(['school_id' => $this->school->getKey()]);
        $application = AdmissionApplication::factory()->create([
            'school_id' => $this->school->getKey(),
            'school_class_id' => $class->getKey(),
            'guardian_name' => 'Sarah Achieng',
            'guardian_phone' => '0755 222 333',
            'guardian_email' => 'sarah@example.com',
            'guardian_relationship' => GuardianRelationship::Mother,
        ]);

        Livewire::test(ListAdmissionApplications::class)
            ->callTableAction('accept', $application)
            ->callTableAction('enrol', $application, data: ['school_class_id' => $class->getKey(), 'residence' => 'day', 'admission_date' => now()->toDateString()])
            ->assertHasNoTableActionErrors();

        $account = ParentAccount::where('phone', '+256755222333')->sole();
        $this->assertSame($this->school->getKey(), $account->school_id);
        Notification::assertSentTo($account, ParentLogin::class);
    }

    public function test_sharebility_adds_a_schools_first_administrator_with_an_emailed_login(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        Filament::setCurrentPanel('admin');
        Filament::bootCurrentPanel();

        Livewire::test(UsersRelationManager::class, ['ownerRecord' => $this->school, 'pageClass' => EditSchool::class])
            ->callTableAction('create', data: ['name' => 'Head Admin', 'email' => 'admin@stmarys.test', 'role' => StaffRole::Admin->value])
            ->assertHasNoTableActionErrors()
            ->assertNotified('Login for Head Admin');

        $user = User::where('email', 'admin@stmarys.test')->sole();
        $this->assertTrue($user->must_change_password);
        $this->assertSame(StaffRole::Admin, $user->roleAt($this->school));
        Notification::assertSentTo($user, StaffLogin::class, fn (StaffLogin $mail): bool => Hash::check($mail->password, $user->password));
    }

    public function test_staff_can_reset_a_forgotten_password_by_email(): void
    {
        $this->get('/school/login')->assertOk()->assertSee('Forgot password?');
        $this->get('/school/password-reset/request')->assertOk();
    }
}
