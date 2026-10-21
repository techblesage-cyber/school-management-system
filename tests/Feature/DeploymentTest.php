<?php

namespace Tests\Feature;

use App\Models\ParentAccount;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Panel;
use App\Models\ResultPublication;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DeploymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_demo_school_has_everything_to_try(): void
    {
        $this->seed(DemoSeeder::class);

        $school = School::query()->where('slug', 'demo')->sole();
        $this->assertSame(24, Student::query()->withoutGlobalScopes()->where('school_id', $school->getKey())->count());
        $this->assertSame(3, ResultPublication::query()->withoutGlobalScopes()->where('school_id', $school->getKey())->count());

        // The demo logins work
        $head = User::query()->where('email', 'head@demo.test')->sole();
        $this->assertTrue($head->canAccessTenant($school));
        $this->actingAs($head)->get('/school/demo')->assertOk();

        $parent = ParentAccount::query()->where('phone', '+256700000001')->sole();
        $this->assertSame(2, $parent->childrenQuery()->count());
        $this->actingAs($parent, 'parent')->get('/parent')->assertOk()->assertSee('Nakato Amina');

        // A second run leaves the demo school alone
        $this->seed(DemoSeeder::class);
        $this->assertSame(1, School::query()->where('slug', 'demo')->count());
    }

    public function test_the_demo_school_is_never_added_to_the_live_server(): void
    {
        $this->app['env'] = 'production';

        // Even when someone forces past Laravel's own "are you sure?"
        $this->artisan('db:seed', ['--class' => DemoSeeder::class, '--force' => true])->assertSuccessful();

        $this->assertSame(0, School::query()->count());
    }

    public function test_a_super_admin_can_be_made_from_a_cron_job(): void
    {
        // Cron jobs can't answer questions, so everything comes as options
        $this->artisan('app:super-admin', ['email' => 'owner@sharebility.test', '--name' => 'Owner', '--password' => 'long-enough-1'])
            ->assertSuccessful();

        $user = User::query()->where('email', 'owner@sharebility.test')->sole();
        $this->assertTrue($user->is_super_admin);
        $this->assertSame('Owner', $user->name);

        $this->artisan('app:super-admin', ['email' => 'short@sharebility.test', '--name' => 'Short', '--password' => 'short'])
            ->assertFailed();
        $this->assertFalse(User::query()->where('email', 'short@sharebility.test')->exists());

        // Running it again with a new password is the way back in after forgetting it
        $this->artisan('app:super-admin', ['email' => 'owner@sharebility.test', '--password' => 'brand-new-pass'])
            ->expectsOutput('Password changed.')
            ->assertSuccessful();
        $this->assertTrue(Hash::check('brand-new-pass', $user->fresh()->password));

        $this->artisan('app:super-admin', ['email' => 'owner@sharebility.test', '--password' => 'tiny'])->assertFailed();
        $this->assertTrue(Hash::check('brand-new-pass', $user->fresh()->password));
    }

    public function test_the_home_page_shows_the_school_on_its_own_subdomain(): void
    {
        config(['school.tenant_domain' => 'sms.sharebility.test']);
        School::factory()->create(['slug' => 'stmarys', 'name' => 'St Marys College', 'motto' => 'Faith and work']);

        $this->get('http://sms.sharebility.test/')
            ->assertOk()
            ->assertSee('A parent')
            ->assertDontSee('St Marys College');

        $this->get('http://stmarys.sms.sharebility.test/')
            ->assertOk()
            ->assertSee('St Marys College')
            ->assertSee('Faith and work');

        // The Sharebility admin panel is never linked from the home page
        foreach (['http://sms.sharebility.test/', 'http://stmarys.sms.sharebility.test/'] as $page) {
            $this->get($page)->assertDontSee('/admin')->assertDontSee('Sharebility');
        }
    }

    public function test_the_admin_panel_can_have_a_private_address_on_the_main_domain_only(): void
    {
        $read = function (?string $value): string {
            $value === null ? putenv('ADMIN_PATH') : putenv("ADMIN_PATH={$value}");
            $_ENV['ADMIN_PATH'] = $_SERVER['ADMIN_PATH'] = $value;

            if ($value === null) {
                unset($_ENV['ADMIN_PATH'], $_SERVER['ADMIN_PATH']);
            }

            return (require config_path('school.php'))['admin_path'];
        };

        $this->assertSame('admin', $read(null));
        $this->assertSame('sharebility-hq-7k2', $read('Sharebility-HQ-7k2'));
        $this->assertSame('hq', $read('/hq/'), 'slashes and odd characters are dropped');
        $this->assertSame('admin', $read('///'));
        $read(null);

        // With school subdomains on, the panel answers on the main domain only
        config(['school.tenant_domain' => 'sms.sharebility.test']);
        $panel = (new AdminPanelProvider($this->app))->panel(Panel::make());
        $this->assertSame(['sms.sharebility.test'], $panel->getDomains());

        config(['school.tenant_domain' => null]);
        $this->assertSame([], (new AdminPanelProvider($this->app))->panel(Panel::make())->getDomains());
    }
}
