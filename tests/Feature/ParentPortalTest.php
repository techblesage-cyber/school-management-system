<?php

namespace Tests\Feature;

use App\Enums\CircularAudience;
use App\Enums\StaffRole;
use App\Filament\Parent\Pages\EditParentProfile;
use App\Filament\Parent\Pages\ParentLogin;
use App\Filament\Parent\Resources\Children\Pages\ListChildren;
use App\Filament\Parent\Resources\Circulars\Pages\ListCirculars;
use App\Filament\Parent\Widgets\ChildrenOverview;
use App\Filament\Parent\Widgets\PerformanceChart;
use App\Filament\School\Resources\Guardians\Pages\ListGuardians;
use App\Models\AcademicTerm;
use App\Models\Assessment;
use App\Models\Circular;
use App\Models\FeeItem;
use App\Models\GradingScale;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\Mark;
use App\Models\ParentAccount;
use App\Models\ResultPublication;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Support\ParentPortal;
use App\Support\PhoneNumber;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ParentPortalTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private AcademicTerm $term;

    private SchoolClass $s1;

    private SchoolClass $s2;

    private Student $child;

    private Student $stranger;

    private Guardian $mother;

    private ParentAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->school = School::factory()->create(['slug' => 'stmarys', 'name' => 'St Marys College']);
        $this->term = AcademicTerm::factory()->create(['school_id' => $this->school->getKey()]);
        $scale = GradingScale::addStandard($this->school, 'O-level (A–E)');
        $this->s1 = SchoolClass::factory()->create(['school_id' => $this->school->getKey(), 'name' => 'S.1', 'grading_scale_id' => $scale->getKey()]);
        $this->s2 = SchoolClass::factory()->create(['school_id' => $this->school->getKey(), 'name' => 'S.2', 'grading_scale_id' => $scale->getKey()]);

        $this->child = Student::factory()->create(['school_id' => $this->school->getKey(), 'school_class_id' => $this->s1->getKey(), 'first_name' => 'Amina', 'last_name' => 'Nakato']);
        $this->stranger = Student::factory()->create(['school_id' => $this->school->getKey(), 'school_class_id' => $this->s2->getKey(), 'first_name' => 'Other', 'last_name' => 'Child']);

        $this->mother = Guardian::factory()->create(['school_id' => $this->school->getKey(), 'name' => 'Grace Nakato', 'phone' => '0772 123 456']);
        $this->child->guardians()->attach($this->mother, ['relationship' => 'mother', 'is_primary' => true]);
        [$this->account] = ParentAccount::grantTo($this->mother);
        $this->account->update(['password' => 'secret-pass']);

        Filament::setCurrentPanel(null);
        ParentPortal::flush();
    }

    private function signInParent(?ParentAccount $account = null): void
    {
        $this->actingAs($account ?? $this->account, 'parent');
        Filament::setCurrentPanel('parent');
        Filament::bootCurrentPanel();
    }

    private function results(Student $student, array $scores): void
    {
        $exam = Assessment::query()->withoutGlobalScopes()->firstOrCreate(
            ['school_id' => $student->school_id, 'academic_term_id' => $this->term->getKey(), 'name' => 'End of term'],
            ['max_score' => 100, 'weight' => 100],
        );

        foreach ($scores as $subject => $score) {
            $subject = Subject::query()->withoutGlobalScopes()->firstOrCreate(['school_id' => $student->school_id, 'name' => $subject]);
            Mark::create([
                'school_id' => $student->school_id, 'assessment_id' => $exam->getKey(), 'student_id' => $student->getKey(),
                'subject_id' => $subject->getKey(), 'school_class_id' => $student->school_class_id, 'score' => $score,
            ]);
        }
    }

    private function publish(SchoolClass $class): void
    {
        ResultPublication::create(['school_id' => $this->school->getKey(), 'academic_term_id' => $this->term->getKey(), 'school_class_id' => $class->getKey()]);
        ParentPortal::flush();
    }

    public function test_phone_numbers_are_written_one_way(): void
    {
        foreach (['0772 123 456', '0772-123456', '772123456', '256772123456', '+256 772 123 456', '00256772123456'] as $typed) {
            $this->assertSame('+256772123456', PhoneNumber::normalize($typed), $typed);
        }

        $this->assertSame('+254712345678', PhoneNumber::normalize('+254 712 345678'));
        $this->assertSame('0772 123 456', PhoneNumber::display('+256772123456'));
        $this->assertSame('+256772123456', $this->account->phone);
    }

    public function test_parents_sign_in_with_their_phone_number_however_it_is_typed(): void
    {
        Filament::setCurrentPanel('parent');

        Livewire::test(ParentLogin::class)
            ->fillForm(['phone' => '0772123456', 'password' => 'wrong'])
            ->call('authenticate')
            ->assertHasFormErrors(['phone']);
        $this->assertFalse(Auth::guard('parent')->check());

        Livewire::test(ParentLogin::class)
            ->fillForm(['phone' => '+256 772 123 456', 'password' => 'secret-pass'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertTrue(Auth::guard('parent')->user()->is($this->account));
        $this->assertNotNull($this->account->refresh()->last_login_at);

        // Signing in as a parent doesn't sign anyone in to the staff portal
        $this->assertFalse(Auth::guard('web')->check());
        $this->get('/school/stmarys')->assertRedirect();
    }

    public function test_parents_with_no_children_cannot_use_the_portal(): void
    {
        $lonely = ParentAccount::create(['name' => 'No Kids', 'phone' => '0700000001', 'password' => 'secret-pass']);

        $this->actingAs($lonely, 'parent')->get('/parent')->assertForbidden();
        $this->actingAs($this->account, 'parent')->get('/parent')->assertOk();
    }

    public function test_parents_only_ever_see_their_own_children(): void
    {
        $this->signInParent();

        Livewire::test(ListChildren::class)
            ->assertCanSeeTableRecords([$this->child])
            ->assertCanNotSeeTableRecords([$this->stranger]);

        $this->get("/parent/children/{$this->child->getKey()}")->assertOk()->assertSee('Nakato Amina');
        $this->get("/parent/children/{$this->stranger->getKey()}")->assertNotFound();

        Filament::setCurrentPanel(null);
        $this->get(route('parents.statement', $this->child))->assertOk()->assertSee('FEES STATEMENT');
        $this->get(route('parents.statement', $this->stranger))->assertNotFound();

        // Staff signed in to the staff portal aren't parents
        Auth::guard('parent')->logout();
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'web')->get(route('parents.statement', $this->child))->assertForbidden();
        $this->actingAs($admin, 'web')->get('/parent')->assertRedirect('/parent/login');
    }

    public function test_report_cards_appear_only_once_published(): void
    {
        $this->results($this->child, ['English' => 85, 'Mathematics' => 72]);
        $this->results($this->stranger, ['English' => 40]);
        $this->signInParent();
        Filament::setCurrentPanel(null);

        $url = route('parents.report-card', ['term' => $this->term->getKey(), 'student' => $this->child->getKey()]);

        $this->assertSame([], ParentPortal::publishedTerms($this->child)->all());
        $this->get($url)->assertNotFound();

        $this->publish($this->s1);

        $this->assertCount(1, ParentPortal::publishedTerms($this->child));
        $this->get($url)->assertOk()->assertSee('TERM REPORT')->assertSee('Nakato Amina')->assertSee('78.5')->assertDontSee('Child Other');
        $this->assertSame(['Term 3, 2026' => 78.5], ParentPortal::averages($this->child));

        // Another family's report card stays out of reach, published or not
        $this->publish($this->s2);
        $this->get(route('parents.report-card', ['term' => $this->term->getKey(), 'student' => $this->stranger->getKey()]))->assertNotFound();

        $this->signInParent();
        $this->get("/parent/children/{$this->child->getKey()}")->assertOk()->assertSee('Open report card')->assertSee('Average 78.5%');
    }

    public function test_the_performance_chart_only_shows_their_own_child(): void
    {
        $this->results($this->child, ['English' => 85]);
        $this->results($this->stranger, ['English' => 40]);
        $this->publish($this->s1);
        $this->publish($this->s2);
        $this->signInParent();

        $chart = fn (Student $student) => invade(Livewire::test(PerformanceChart::class, ['record' => $student])->instance())->getData();

        $this->assertSame([85.0], $chart($this->child)['datasets'][0]['data']);
        $this->assertSame([], $chart($this->stranger)['datasets'][0]['data']);
    }

    public function test_one_account_covers_children_at_several_schools(): void
    {
        $other = School::factory()->create(['name' => 'Kings Primary']);
        $sibling = Student::factory()->create(['school_id' => $other->getKey(), 'first_name' => 'Baby', 'last_name' => 'Nakato']);
        $sameMother = Guardian::factory()->create(['school_id' => $other->getKey(), 'name' => 'Grace N.', 'phone' => '+256772123456']);
        $sibling->guardians()->attach($sameMother, ['relationship' => 'mother', 'is_primary' => true]);

        [$account, $password] = ParentAccount::grantTo($sameMother);
        $this->assertTrue($account->is($this->account));
        $this->assertNull($password, 'an existing account keeps its password');
        $this->assertTrue(Hash::check('secret-pass', $account->refresh()->password));

        $this->signInParent();
        Livewire::test(ChildrenOverview::class)->assertCanSeeTableRecords([$this->child, $sibling]);

        // A school switched off is hidden
        $other->update(['is_active' => false]);
        Livewire::test(ChildrenOverview::class)->assertCanSeeTableRecords([$this->child])->assertCanNotSeeTableRecords([$sibling]);
    }

    public function test_parents_see_circulars_meant_for_their_children(): void
    {
        $make = fn (array $attributes) => Circular::create([
            'school_id' => $this->school->getKey(), 'title' => 'Notice', 'body' => '<p>Hi</p>',
            'audience' => CircularAudience::Parents, 'published_at' => now()->subHour(), ...$attributes,
        ]);

        $forAll = $make(['title' => 'Visiting day', 'attachment' => 'circulars/visit.pdf', 'attachment_name' => 'visit.pdf']);
        $forS1 = $make(['title' => 'S.1 trip']);
        $forS1->classes()->attach($this->s1);
        $forS2 = $make(['title' => 'S.2 trip', 'attachment' => 'circulars/s2.pdf']);
        $forS2->classes()->attach($this->s2);
        $draft = $make(['title' => 'Draft', 'published_at' => null]);
        $staffOnly = $make(['title' => 'Staff meeting', 'audience' => CircularAudience::Staff]);
        $otherSchool = Circular::create(['school_id' => School::factory()->create()->getKey(), 'title' => 'Elsewhere', 'body' => 'x', 'audience' => CircularAudience::Parents, 'published_at' => now()->subHour()]);
        Storage::disk('local')->put('circulars/visit.pdf', 'pdf');
        Storage::disk('local')->put('circulars/s2.pdf', 'pdf');

        $this->signInParent();

        Livewire::test(ListCirculars::class)
            ->assertCanSeeTableRecords([$forAll, $forS1])
            ->assertCanNotSeeTableRecords([$forS2, $draft, $staffOnly, $otherSchool]);

        $this->get("/parent/circulars/{$forS2->getKey()}")->assertNotFound();
        $this->get("/parent/circulars/{$forAll->getKey()}")->assertOk()->assertSee('Visiting day');

        Filament::setCurrentPanel(null);
        $this->get(route('circulars.attachment', $forAll))->assertOk()->assertDownload('visit.pdf');
        $this->get(route('circulars.attachment', $forS2))->assertForbidden();
    }

    public function test_parents_change_their_own_password(): void
    {
        $this->signInParent();

        Livewire::test(EditParentProfile::class)
            ->fillForm(['name' => 'Grace Nakato', 'password' => 'my-new-password', 'passwordConfirmation' => 'my-new-password', 'currentPassword' => 'secret-pass'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(Hash::check('my-new-password', $this->account->refresh()->password));
        $this->assertSame('+256772123456', $this->account->phone);
    }

    public function test_staff_give_reset_and_remove_portal_access(): void
    {
        $father = Guardian::factory()->create(['school_id' => $this->school->getKey(), 'name' => 'Robert Okello', 'phone' => '0701 000111']);
        $this->stranger->guardians()->attach($father, ['relationship' => 'father', 'is_primary' => true]);

        $secretary = User::factory()->create();
        $this->school->users()->attach($secretary, ['role' => StaffRole::Secretary->value]);
        $this->actingAs($secretary);
        Filament::setCurrentPanel('school');
        Filament::setTenant($this->school);
        Filament::bootCurrentPanel();

        Livewire::test(ListGuardians::class)
            ->callTableAction('givePortalAccess', $father)
            ->assertHasNoTableActionErrors()
            ->assertNotified();

        $account = ParentAccount::where('phone', '+256701000111')->firstOrFail();
        $this->assertTrue($father->refresh()->parentAccount->is($account));
        $this->assertSame('Robert Okello', $account->name);

        $oldHash = $account->password;
        Livewire::test(ListGuardians::class)->callTableAction('resetPortalPassword', $father);
        $this->assertNotSame($oldHash, $account->refresh()->password);

        Livewire::test(ListGuardians::class)->callTableAction('removePortalAccess', $father);
        $this->assertNull($father->refresh()->parent_account_id);
        $this->assertFalse($account->canAccessPanel(Filament::getPanel('parent')));

        // Teachers and bursars can't hand out logins
        foreach ([StaffRole::Teacher, StaffRole::Bursar] as $role) {
            $user = User::factory()->create();
            $this->school->users()->attach($user, ['role' => $role->value]);
            $this->actingAs($user);
            Livewire::test(ListGuardians::class)->assertTableActionHidden('givePortalAccess', $father);
        }
    }

    public function test_fees_shown_to_parents_match_the_school_records(): void
    {
        FeeItem::create(['school_id' => $this->school->getKey(), 'academic_term_id' => $this->term->getKey(), 'name' => 'Tuition', 'amount' => 800_000]);
        Invoice::billTerm($this->term);

        $this->signInParent();
        $this->get("/parent/children/{$this->child->getKey()}")->assertOk()->assertSee('UGX 800,000');
        Livewire::test(ChildrenOverview::class)->assertSee('UGX 800,000');
    }
}
