<?php

namespace Tests\Feature;

use App\Enums\StaffRole;
use App\Filament\School\Pages\ClassFees;
use App\Models\AcademicTerm;
use App\Models\FeeItem;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Stream;
use App\Models\Student;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClassFeesTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private AcademicTerm $term;

    private SchoolClass $s1;

    private SchoolClass $s2;

    private Stream $east;

    private Stream $west;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::factory()->create(['slug' => 'stmarys']);
        $this->term = AcademicTerm::factory()->create(['school_id' => $this->school->getKey()]);
        $this->s1 = SchoolClass::factory()->create(['school_id' => $this->school->getKey(), 'name' => 'S.1', 'sort_order' => 1]);
        $this->s2 = SchoolClass::factory()->create(['school_id' => $this->school->getKey(), 'name' => 'S.2', 'sort_order' => 2]);
        $this->east = $this->s1->streams()->create(['name' => 'East']);
        $this->west = $this->s1->streams()->create(['name' => 'West']);

        // S.1 pays 1,000,000 tuition, S.2 pays 1,200,000, and everyone pays 50,000
        $this->fee('Tuition', 1_000_000, [$this->s1]);
        $this->fee('Tuition', 1_200_000, [$this->s2]);
        $this->fee('Development', 50_000, []);

        $this->signInAs($this->staff(StaffRole::Bursar));
    }

    private function fee(string $name, int $amount, array $classes): void
    {
        $item = FeeItem::create(['school_id' => $this->school->getKey(), 'academic_term_id' => $this->term->getKey(), 'name' => $name, 'amount' => $amount]);
        $item->classes()->attach(collect($classes)->map->getKey()->all());
    }

    private function student(SchoolClass $class, ?Stream $stream = null): Student
    {
        return Student::factory()->create(['school_id' => $this->school->getKey(), 'school_class_id' => $class->getKey(), 'stream_id' => $stream?->getKey()]);
    }

    private function pay(Student $student, int $amount, ?AcademicTerm $term = null): void
    {
        Payment::create(['school_id' => $this->school->getKey(), 'student_id' => $student->getKey(), 'amount' => $amount, 'method' => 'cash', 'academic_term_id' => ($term ?? $this->term)->getKey()]);
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

    public function test_each_stream_shows_its_students_fees_payments_and_balances(): void
    {
        $amina = $this->student($this->s1, $this->east);
        $brian = $this->student($this->s1, $this->east);
        $westPupil = $this->student($this->s1, $this->west);
        $s2Pupil = $this->student($this->s2);
        Invoice::billTerm($this->term);
        $this->pay($amina, 1_050_000);
        $this->pay($brian, 400_000);

        $this->assertSame(1_050_000, $amina->feeBalance() + 1_050_000, 'S.1 bill: tuition + development');
        $this->assertSame(1_250_000, $s2Pupil->feeBalance(), 'S.2 bill: its own tuition + development');

        $page = Livewire::test(ClassFees::class, ['group' => 's:'.$this->east->getKey()])
            ->assertCanSeeTableRecords([$amina, $brian])
            ->assertCanNotSeeTableRecords([$westPupil, $s2Pupil])
            ->assertTableColumnFormattedStateSet('term_fees', 'UGX 1,050,000', $brian)
            ->assertTableColumnFormattedStateSet('term_paid', 'UGX 400,000', $brian)
            ->assertTableColumnFormattedStateSet('fee_balance', 'UGX 650,000', $brian)
            ->assertTableColumnFormattedStateSet('fee_balance', 'Cleared', $amina)
            ->assertSee('S.1 fees for Term 3, 2026: Day UGX 1,050,000');

        $tiles = collect($page->instance()->tiles())->keyBy('name');
        $east = collect($tiles['S.1']['tiles'])->firstWhere('name', 'East');
        $this->assertSame(['students' => 2, 'billed' => 2_100_000, 'paid' => 1_450_000, 'owed' => 650_000, 'percent' => 69], array_intersect_key($east, array_flip(['students', 'billed', 'paid', 'owed', 'percent'])));
        $this->assertSame(['Whole class', 'East', 'West'], collect($tiles['S.1']['tiles'])->pluck('name')->all());
        $this->assertSame(['All students'], collect($tiles['S.2']['tiles'])->pluck('name')->all(), 'a class without streams has one tile');

        // The whole class
        Livewire::test(ClassFees::class, ['group' => 'c:'.$this->s1->getKey()])
            ->assertCanSeeTableRecords([$amina, $brian, $westPupil])
            ->assertCanNotSeeTableRecords([$s2Pupil]);
    }

    public function test_balances_include_what_was_owed_from_before(): void
    {
        $amina = $this->student($this->s1, $this->east);
        $lastTerm = AcademicTerm::factory()->create(['school_id' => $this->school->getKey(), 'term' => 2, 'is_current' => false]);
        Invoice::query()->withoutGlobalScopes()->create(['school_id' => $this->school->getKey(), 'student_id' => $amina->getKey(), 'academic_term_id' => $lastTerm->getKey()])
            ->items()->create(['description' => 'Tuition', 'amount' => 300_000]);
        Invoice::query()->withoutGlobalScopes()->where('academic_term_id', $lastTerm->getKey())->first()->recalculateTotal();
        Invoice::billTerm($this->term);

        Livewire::test(ClassFees::class, ['group' => 's:'.$this->east->getKey()])
            ->assertTableColumnFormattedStateSet('term_fees', 'UGX 1,050,000', $amina)
            ->assertTableColumnFormattedStateSet('fee_balance', 'UGX 1,350,000', $amina)
            ->assertSee('Includes UGX 300,000 from before');
    }

    public function test_the_bursar_records_a_payment_from_the_row(): void
    {
        $amina = $this->student($this->s1, $this->east);
        Invoice::billTerm($this->term);

        Livewire::test(ClassFees::class, ['group' => 's:'.$this->east->getKey()])
            ->callTableAction('pay', $amina, data: ['amount' => 600_000, 'paid_on' => today()->toDateString(), 'method' => 'cash', 'academic_term_id' => $this->term->getKey()])
            ->assertHasNoTableActionErrors()
            ->assertNotified()
            ->assertTableColumnFormattedStateSet('term_paid', 'UGX 600,000', $amina)
            ->assertTableColumnFormattedStateSet('fee_balance', 'UGX 450,000', $amina);

        $this->assertSame(450_000, $amina->feeBalance());
    }

    public function test_students_without_a_bill_can_be_billed_from_here(): void
    {
        $amina = $this->student($this->s1, $this->east);

        Livewire::test(ClassFees::class, ['group' => 's:'.$this->east->getKey()])
            ->assertTableColumnFormattedStateSet('term_fees', 'Not billed', $amina)
            ->assertSee('1 student here has no bill for Term 3, 2026 yet')
            ->call('billUnbilled')
            ->assertNotified('Billed 1 student for Term 3, 2026')
            ->assertTableColumnFormattedStateSet('term_fees', 'UGX 1,050,000', $amina);

        // Head teachers can look but not bill or take money
        $this->signInAs($this->staff(StaffRole::HeadTeacher));
        Livewire::test(ClassFees::class, ['group' => 's:'.$this->east->getKey()])
            ->assertTableActionHidden('pay', $amina)
            ->call('billUnbilled')
            ->assertForbidden();
    }

    public function test_only_fees_staff_can_open_it_and_other_schools_classes_show_nothing(): void
    {
        foreach ([StaffRole::Teacher, StaffRole::Secretary] as $role) {
            $this->actingAs($this->staff($role))->get('/school/stmarys/class-fees')->assertForbidden();
        }

        $this->actingAs($this->staff(StaffRole::Bursar))->get('/school/stmarys/class-fees')->assertOk()->assertSee('Whole class');

        // Another school's class, made outside the portal so it really is theirs
        Filament::setCurrentPanel(null);
        $other = School::factory()->create();
        $otherClass = SchoolClass::factory()->create(['school_id' => $other->getKey()]);
        $outsider = Student::factory()->create(['school_id' => $other->getKey(), 'school_class_id' => $otherClass->getKey()]);
        $this->assertSame($other->getKey(), $outsider->fresh()->school_id);

        $this->signInAs($this->staff(StaffRole::Bursar));
        Livewire::test(ClassFees::class, ['group' => 'c:'.$otherClass->getKey()])
            ->assertCanNotSeeTableRecords([$outsider])
            ->assertDontSee($outsider->full_name);
    }
}
