<?php

namespace Tests\Feature;

use App\Enums\CircularAudience;
use App\Enums\StaffRole;
use App\Filament\School\Resources\Circulars\Pages\CreateCircular;
use App\Filament\School\Resources\Circulars\Pages\ListCirculars;
use App\Filament\School\Widgets\LatestCirculars;
use App\Models\Circular;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CircularsTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $secretary;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->school = School::factory()->create(['slug' => 'stmarys']);
        $this->secretary = $this->staff(StaffRole::Secretary);
        $this->signInAs($this->secretary);
    }

    private function staff(StaffRole $role, ?School $school = null): User
    {
        $user = User::factory()->create();
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

    private function circular(array $attributes = []): Circular
    {
        return Circular::create([
            'school_id' => $this->school->getKey(),
            'title' => 'Visiting day',
            'body' => '<p>Visiting day is on <strong>Sunday</strong>.</p>',
            'audience' => CircularAudience::Parents,
            'published_at' => now()->subHour(),
            ...$attributes,
        ]);
    }

    public function test_a_circular_is_written_for_some_classes_with_an_attachment(): void
    {
        // With no classes yet, the form says so rather than showing an empty list
        Livewire::test(CreateCircular::class)->assertSee('This school has no classes yet');

        $s1 = SchoolClass::factory()->create(['school_id' => $this->school->getKey(), 'name' => 'S.1']);
        $s2 = SchoolClass::factory()->create(['school_id' => $this->school->getKey(), 'name' => 'S.2']);
        $otherSchool = School::factory()->create();
        DB::table('school_classes')->insert(['school_id' => $otherSchool->getKey(), 'name' => 'P.7 Elsewhere', 'sort_order' => 1]);

        // Every class of this school is shown as a tick box, no other school's
        Livewire::test(CreateCircular::class)
            ->assertSee('Tick the classes this is for')
            ->assertSeeHtml('value="'.$s1->getKey().'"')
            ->assertSeeHtml('value="'.$s2->getKey().'"')
            ->assertDontSee('P.7 Elsewhere')
            ->fillForm([
                'title' => 'S.1 science trip',
                'audience' => CircularAudience::Parents->value,
                'classes' => [$s1->getKey()],
                'body' => '<p>Please pay UGX 20,000 by Friday.</p>',
                'attachment' => UploadedFile::fake()->create('trip-letter.pdf', 120, 'application/pdf'),
                'published_at' => now()->subMinute()->format('Y-m-d H:i'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $circular = Circular::firstOrFail();
        $this->assertSame([$s1->getKey()], $circular->classes->modelKeys());
        $this->assertSame('trip-letter.pdf', $circular->attachment_name);
        Storage::disk('local')->assertExists($circular->attachment);
        $this->assertTrue($circular->createdBy->is($this->secretary));
        $this->assertTrue($circular->isPublished());

        // Only S.1 parents get it
        $s1Child = Student::factory()->create(['school_id' => $this->school->getKey(), 'school_class_id' => $s1->getKey()]);
        $s2Child = Student::factory()->create(['school_id' => $this->school->getKey(), 'school_class_id' => $s2->getKey()]);
        $this->assertTrue(Circular::query()->forParentsOf([$s1Child])->exists());
        $this->assertFalse(Circular::query()->forParentsOf([$s2Child])->exists());

        // Staff who may read it can download the attachment
        Filament::setCurrentPanel(null);
        $this->get(route('circulars.attachment', $circular))->assertOk()->assertDownload('trip-letter.pdf');
    }

    public function test_drafts_and_future_circulars_are_not_published(): void
    {
        $draft = $this->circular(['published_at' => null]);
        $later = $this->circular(['published_at' => now()->addDay()]);
        $now = $this->circular();

        $this->assertFalse($draft->isPublished());
        $this->assertFalse($later->isPublished());
        $this->assertSame([$now->getKey()], Circular::published()->pluck('id')->all());

        Livewire::test(ListCirculars::class)
            ->callTableAction('publish', $draft)
            ->assertHasNoTableActionErrors();

        $this->assertTrue($draft->refresh()->isPublished());
    }

    public function test_teachers_read_staff_circulars_but_cannot_write_or_see_parent_ones(): void
    {
        $forStaff = $this->circular(['title' => 'Staff meeting', 'audience' => CircularAudience::Staff]);
        $forParents = $this->circular(['title' => 'Visiting day']);
        $staffDraft = $this->circular(['title' => 'Draft for staff', 'audience' => CircularAudience::Staff, 'published_at' => null, 'attachment' => 'circulars/x.pdf']);
        Storage::disk('local')->put('circulars/x.pdf', 'x');

        $this->signInAs($this->staff(StaffRole::Teacher));

        Livewire::test(ListCirculars::class)
            ->assertCanSeeTableRecords([$forStaff])
            ->assertCanNotSeeTableRecords([$forParents, $staffDraft]);

        $this->get('/school/stmarys/circulars/create')->assertForbidden();
        $this->get("/school/stmarys/circulars/{$forParents->getKey()}")->assertNotFound();
        $this->get("/school/stmarys/circulars/{$forStaff->getKey()}")->assertOk()->assertSee('Staff meeting');

        Livewire::test(LatestCirculars::class)->assertCanSeeTableRecords([$forStaff])->assertCanNotSeeTableRecords([$forParents, $staffDraft]);

        Filament::setCurrentPanel(null);
        $this->get(route('circulars.attachment', $staffDraft))->assertForbidden();
    }

    public function test_circular_text_is_cleaned_of_scripts(): void
    {
        $circular = $this->circular(['body' => '<p>Hello</p><script>alert(1)</script><img src=x onerror=alert(2)>']);

        $this->assertStringNotContainsString('<script', $circular->safeBody());
        $this->assertStringNotContainsString('onerror', $circular->safeBody());
        $this->assertStringContainsString('Hello', $circular->safeBody());

        $this->get("/school/stmarys/circulars/{$circular->getKey()}")->assertOk()->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_another_schools_staff_cannot_reach_circulars(): void
    {
        $circular = $this->circular(['audience' => CircularAudience::Everyone, 'attachment' => 'circulars/y.pdf']);
        Storage::disk('local')->put('circulars/y.pdf', 'y');

        $outsider = $this->staff(StaffRole::Admin, School::factory()->create());
        Filament::setCurrentPanel(null);
        $this->actingAs($outsider)->get(route('circulars.attachment', $circular))->assertForbidden();
    }
}
