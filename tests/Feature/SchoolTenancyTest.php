<?php

namespace Tests\Feature;

use App\Enums\StaffRole;
use App\Filament\Admin\Resources\Schools\Pages\CreateSchool;
use App\Filament\Admin\Resources\Schools\Pages\EditSchool;
use App\Filament\Admin\Resources\Schools\Pages\ListSchools;
use App\Filament\Admin\Resources\Schools\RelationManagers\UsersRelationManager;
use App\Filament\School\Pages\Tenancy\EditSchoolProfile;
use App\Models\School;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SchoolTenancyTest extends TestCase
{
    use RefreshDatabase;

    private function staff(School $school, StaffRole $role = StaffRole::Teacher): User
    {
        $user = User::factory()->create();
        $school->users()->attach($user, ['role' => $role->value]);

        return $user;
    }

    public function test_only_super_admins_can_open_the_admin_panel(): void
    {
        $school = School::factory()->create();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/admin/schools')
            ->assertOk();

        $this->actingAs($this->staff($school))
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_admin_school_pages_load(): void
    {
        $school = School::factory()->create();
        $this->staff($school, StaffRole::Admin);

        $this->actingAs(User::factory()->superAdmin()->create());

        $this->get('/admin/schools/create')->assertOk();
        $this->get("/admin/schools/{$school->getKey()}/edit")->assertOk()->assertSee($school->name);

        Filament::setCurrentPanel('admin');
        $closed = School::factory()->inactive()->create();

        Livewire::test(ListSchools::class)
            ->assertCanSeeTableRecords([$school, $closed])
            ->assertTableColumnStateSet('users_count', 1, $school)
            ->assertTableActionVisible('openPortal', $school)
            ->assertTableActionHidden('openPortal', $closed)
            ->assertTableActionHasUrl('openPortal', url("/school/{$school->slug}"), $school);
    }

    public function test_super_admin_command_promotes_or_creates_a_user(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com']);

        $this->artisan('app:super-admin', ['email' => 'ME@example.com'])->assertSuccessful();
        $this->assertTrue($user->refresh()->isSuperAdmin());

        $this->artisan('app:super-admin', ['email' => 'new@example.com'])
            ->expectsQuestion('Name', 'New Admin')
            ->expectsQuestion('Password (at least 8 characters)', 'long-enough')
            ->assertSuccessful();
        $this->assertTrue(User::where('email', 'new@example.com')->firstOrFail()->isSuperAdmin());
    }

    public function test_staff_can_only_open_their_own_school(): void
    {
        $stMarys = School::factory()->create(['name' => 'St Marys', 'slug' => 'stmarys']);
        $kings = School::factory()->create(['name' => 'Kings College', 'slug' => 'kings']);
        $teacher = $this->staff($stMarys);

        $this->actingAs($teacher)->get('/school/stmarys')->assertOk()->assertSee('St Marys');
        $this->actingAs($teacher)->get('/school/kings')->assertNotFound();
        $this->actingAs($teacher)->get('/school')->assertRedirect('/school/stmarys');
    }

    public function test_inactive_schools_are_closed_to_their_staff(): void
    {
        $school = School::factory()->inactive()->create(['slug' => 'closed']);
        $teacher = $this->staff($school);

        $this->actingAs($teacher)->get('/school/closed')->assertForbidden();
    }

    public function test_users_without_a_school_cannot_open_the_school_panel(): void
    {
        School::factory()->create(['slug' => 'stmarys']);

        $this->actingAs(User::factory()->create())->get('/school/stmarys')->assertForbidden();
    }

    public function test_super_admins_can_open_any_active_school(): void
    {
        School::factory()->create(['slug' => 'stmarys']);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/school/stmarys')
            ->assertOk();
    }

    public function test_super_admin_creates_a_school(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        Filament::setCurrentPanel('admin');

        Livewire::test(CreateSchool::class)
            ->fillForm(['name' => "St. Mary's College", 'slug' => 'st-marys-college', 'phone' => '0700000000'])
            ->call('create')
            ->assertHasNoFormErrors();

        $school = School::where('slug', 'st-marys-college')->firstOrFail();
        $this->assertSame("St. Mary's College", $school->name);
        $this->assertTrue($school->is_active);
    }

    public function test_school_web_address_names_are_validated(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        Filament::setCurrentPanel('admin');
        School::factory()->create(['slug' => 'taken']);

        foreach (['www', 'taken', 'Has Spaces', 'under_score', '-dash-'] as $slug) {
            Livewire::test(CreateSchool::class)
                ->fillForm(['name' => 'Test School', 'slug' => $slug])
                ->call('create')
                ->assertHasFormErrors(['slug']);
        }
    }

    public function test_super_admin_adds_new_and_existing_staff_with_roles(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        Filament::setCurrentPanel('admin');
        $school = School::factory()->create();
        $existing = User::factory()->create(['email' => 'existing@example.com']);

        $manager = Livewire::test(UsersRelationManager::class, [
            'ownerRecord' => $school,
            'pageClass' => EditSchool::class,
        ]);

        $manager->callTableAction('create', data: [
            'name' => 'Jane Bursar',
            'email' => 'jane@example.com',
            'password' => 'secret-pass',
            'role' => StaffRole::Bursar->value,
        ])->assertHasNoTableActionErrors();

        $manager->callTableAction('attach', data: [
            'recordId' => $existing->getKey(),
            'role' => StaffRole::HeadTeacher->value,
        ])->assertHasNoTableActionErrors();

        $jane = User::where('email', 'jane@example.com')->firstOrFail();
        $this->assertFalse($jane->isSuperAdmin());
        $this->assertSame('bursar', $school->users()->whereKey($jane)->first()->pivot->role);
        $this->assertSame('head_teacher', $school->users()->whereKey($existing)->first()->pivot->role);

        $manager->assertCanSeeTableRecords([$jane, $existing])->assertSee('Head teacher');
    }

    public function test_staff_password_is_kept_when_left_empty_on_edit(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        Filament::setCurrentPanel('admin');
        $school = School::factory()->create();
        $teacher = $this->staff($school);
        $oldHash = $teacher->password;

        Livewire::test(UsersRelationManager::class, ['ownerRecord' => $school, 'pageClass' => EditSchool::class])
            ->callTableAction('edit', $teacher, data: [
                'name' => 'Renamed Teacher',
                'password' => '',
                'role' => StaffRole::Secretary->value,
            ])
            ->assertHasNoTableActionErrors();

        $teacher->refresh();
        $this->assertSame('Renamed Teacher', $teacher->name);
        $this->assertSame($oldHash, $teacher->password);
        $this->assertSame('secretary', $school->users()->whereKey($teacher)->first()->pivot->role);
    }

    public function test_only_school_admins_can_edit_the_school_profile(): void
    {
        $school = School::factory()->create(['slug' => 'stmarys']);
        $admin = $this->staff($school, StaffRole::Admin);
        $teacher = $this->staff($school, StaffRole::Teacher);

        $this->actingAs($teacher)->get('/school/stmarys/profile')->assertNotFound();
        $this->actingAs($admin)->get('/school/stmarys/profile')->assertOk();

        Filament::setCurrentPanel('school');
        Filament::setTenant($school);

        Livewire::test(EditSchoolProfile::class)
            ->fillForm(['name' => 'St Marys SS', 'motto' => 'Light and truth'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Light and truth', $school->refresh()->motto);
    }
}
