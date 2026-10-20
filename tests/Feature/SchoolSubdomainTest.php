<?php

namespace Tests\Feature;

use App\Enums\StaffRole;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Live-server mode: SCHOOL_TENANT_DOMAIN is set, so each school opens on
 * its own subdomain instead of /school/{slug}.
 */
class SchoolSubdomainTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $_SERVER['SCHOOL_TENANT_DOMAIN'] = $_ENV['SCHOOL_TENANT_DOMAIN'] = 'sms.test';

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($_SERVER['SCHOOL_TENANT_DOMAIN'], $_ENV['SCHOOL_TENANT_DOMAIN']);
    }

    public function test_each_school_opens_on_its_own_subdomain(): void
    {
        $stMarys = School::factory()->create(['name' => 'St Marys', 'slug' => 'stmarys']);
        School::factory()->create(['slug' => 'kings']);
        $teacher = User::factory()->create();
        $stMarys->users()->attach($teacher, ['role' => StaffRole::Teacher->value]);

        $this->actingAs($teacher)->get('http://stmarys.sms.test/school')->assertOk()->assertSee('St Marys');
        $this->actingAs($teacher)->get('http://kings.sms.test/school')->assertNotFound();
        $this->actingAs($teacher)->get('http://stmarys.sms.test/school/profile')->assertNotFound();
    }

    public function test_school_login_page_works_on_a_school_subdomain(): void
    {
        School::factory()->create(['slug' => 'stmarys']);

        $this->get('http://stmarys.sms.test/school/login')->assertOk();
    }
}
