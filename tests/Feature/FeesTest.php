<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\Residence;
use App\Enums\StaffRole;
use App\Enums\StudentStatus;
use App\Filament\School\Pages\FeeBalances;
use App\Filament\School\Resources\AcademicTerms\Pages\ManageAcademicTerms;
use App\Filament\School\Resources\FeeItems\Pages\ManageFeeItems;
use App\Filament\School\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\School\Resources\Invoices\Pages\EditInvoice;
use App\Filament\School\Resources\Invoices\Pages\ListInvoices;
use App\Filament\School\Resources\Payments\Pages\CreatePayment;
use App\Filament\School\Resources\Payments\Pages\ListPayments;
use App\Filament\School\Resources\Students\Pages\EditStudent;
use App\Filament\School\Resources\Students\Pages\ViewStudent;
use App\Filament\School\Resources\Students\RelationManagers\InvoicesRelationManager;
use App\Filament\School\Resources\Students\RelationManagers\PaymentsRelationManager;
use App\Filament\School\Widgets\FeesOverview;
use App\Models\AcademicTerm;
use App\Models\FeeItem;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FeesTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $bursar;

    private AcademicTerm $term;

    private SchoolClass $s1;

    private SchoolClass $s5;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::factory()->create(['slug' => 'stmarys', 'name' => 'St Marys College']);
        $this->bursar = $this->staff(StaffRole::Bursar);
        $this->term = AcademicTerm::factory()->create(['school_id' => $this->school->getKey(), 'year' => 2026, 'term' => 3]);
        $this->s1 = SchoolClass::factory()->create(['school_id' => $this->school->getKey(), 'name' => 'S.1']);
        $this->s5 = SchoolClass::factory()->create(['school_id' => $this->school->getKey(), 'name' => 'S.5']);

        $this->signInAs($this->bursar);
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

    /**
     * Tuition 800k for S.1, 950k for S.5, development 100k for all,
     * boarding 600k for boarders only.
     */
    private function feeStructure(?AcademicTerm $term = null): void
    {
        $term ??= $this->term;
        $item = fn (string $name, int $amount, ?Residence $residence = null, array $classes = []) => tap(
            FeeItem::create(['school_id' => $this->school->getKey(), 'academic_term_id' => $term->getKey(), 'name' => $name, 'amount' => $amount, 'residence' => $residence]),
            fn (FeeItem $item) => $item->classes()->sync($classes),
        );

        $item('Tuition', 800_000, classes: [$this->s1->getKey()]);
        $item('Tuition (A-level)', 950_000, classes: [$this->s5->getKey()]);
        $item('Development fee', 100_000);
        $item('Boarding', 600_000, Residence::Boarding);
    }

    private function student(SchoolClass $class, Residence $residence = Residence::Day, array $extra = []): Student
    {
        return Student::factory()->create([
            'school_id' => $this->school->getKey(),
            'school_class_id' => $class->getKey(),
            'residence' => $residence,
            ...$extra,
        ]);
    }

    public function test_only_one_term_is_current(): void
    {
        Livewire::test(ManageAcademicTerms::class)
            ->callAction('create', data: ['year' => 2027, 'term' => 1, 'is_current' => true])
            ->assertHasNoActionErrors()
            ->callAction('create', data: ['year' => 2027, 'term' => 1, 'is_current' => false])
            ->assertHasActionErrors(['term']);

        $this->assertFalse($this->term->refresh()->is_current);
        $this->assertSame('Term 1, 2027', AcademicTerm::currentFor($this->school)->name);
    }

    public function test_billing_follows_class_and_boarding_and_only_bills_each_student_once(): void
    {
        $this->feeStructure();
        $dayS1 = $this->student($this->s1);
        $boarderS5 = $this->student($this->s5, Residence::Boarding);
        $leftSchool = $this->student($this->s1, extra: ['status' => StudentStatus::Left]);

        Livewire::test(ListInvoices::class)
            ->callAction('billStudents', data: ['academic_term_id' => $this->term->getKey()])
            ->assertHasNoActionErrors()
            ->assertNotified('Created 2 invoices for Term 3, 2026');

        $this->assertSame(900_000, $dayS1->invoices()->first()->total);
        $this->assertSame(1_650_000, $boarderS5->invoices()->first()->total);
        $this->assertSame(
            ['Boarding', 'Development fee', 'Tuition (A-level)'],
            $boarderS5->invoices()->first()->items()->pluck('description')->sort()->values()->all(),
        );
        $this->assertSame(0, $leftSchool->invoices()->count());
        $this->assertSame('INV-'.now()->year.'-0001', Invoice::orderBy('id')->first()->number);

        // A student admitted later is billed by running it again; others aren't billed twice
        $newcomer = $this->student($this->s1, Residence::Boarding);

        Livewire::test(ListInvoices::class)
            ->callAction('billStudents', data: ['academic_term_id' => $this->term->getKey()])
            ->assertNotified('Created 1 invoice for Term 3, 2026');

        $this->assertSame(1_500_000, $newcomer->invoices()->first()->total);
        $this->assertSame(3, Invoice::count());
    }

    public function test_billing_needs_a_fee_structure_first(): void
    {
        $this->student($this->s1);

        Livewire::test(ListInvoices::class)
            ->callAction('billStudents', data: ['academic_term_id' => $this->term->getKey()])
            ->assertNotified('No fee structure for Term 3, 2026 yet');

        $this->assertSame(0, Invoice::count());
    }

    public function test_fees_are_copied_from_another_term(): void
    {
        $this->feeStructure();
        $next = AcademicTerm::factory()->create(['school_id' => $this->school->getKey(), 'year' => 2027, 'term' => 1]);

        Livewire::test(ManageFeeItems::class)
            ->callAction('copyFromTerm', data: ['from_term_id' => $this->term->getKey(), 'to_term_id' => $next->getKey()])
            ->assertHasNoActionErrors();

        $copied = $next->feeItems()->with('classes')->get()->keyBy('name');
        $this->assertCount(4, $copied);
        $this->assertSame([$this->s5->getKey()], $copied['Tuition (A-level)']->classes->modelKeys());
        $this->assertSame(Residence::Boarding, $copied['Boarding']->residence);
    }

    public function test_a_manual_invoice_is_filled_from_the_fee_structure_and_can_get_a_bursary(): void
    {
        Repeater::fake();
        $this->feeStructure();
        $student = $this->student($this->s1);

        Livewire::test(CreateInvoice::class)
            ->fillForm(['student_id' => $student->getKey(), 'academic_term_id' => $this->term->getKey()])
            ->call('create')
            ->assertHasNoFormErrors();

        $invoice = $student->invoices()->firstOrFail();
        $this->assertSame(900_000, $invoice->total);

        // A second invoice for the same term is refused
        Livewire::test(CreateInvoice::class)
            ->fillForm(['student_id' => $student->getKey(), 'academic_term_id' => $this->term->getKey()])
            ->call('create')
            ->assertHasFormErrors(['student_id']);

        // Add a bursary as a minus line
        $items = $invoice->items()->orderBy('id')->get()->map(fn ($item) => ['description' => $item->description, 'amount' => $item->amount])->all();

        Livewire::test(EditInvoice::class, ['record' => $invoice->getKey()])
            ->fillForm(['items' => [...$items, ['description' => 'Bursary (50% tuition)', 'amount' => -400_000]]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(500_000, $invoice->refresh()->total);
        $this->assertSame(500_000, $student->feeBalance());
    }

    public function test_payments_get_receipts_reduce_the_balance_and_can_only_be_voided(): void
    {
        $this->feeStructure();
        $student = $this->student($this->s1);
        Invoice::billTerm($this->term);

        Livewire::test(CreatePayment::class)
            ->fillForm([
                'student_id' => $student->getKey(),
                'amount' => 300_000,
                'method' => PaymentMethod::MtnMobileMoney->value,
                'reference' => '',
            ])
            ->call('create')
            ->assertHasFormErrors(['reference' => 'required']);

        Livewire::test(CreatePayment::class)
            ->fillForm([
                'student_id' => $student->getKey(),
                'amount' => 300_000,
                'method' => PaymentMethod::MtnMobileMoney->value,
                'reference' => 'MP261020.1530.A12345',
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $payment = Payment::firstOrFail();
        $this->assertSame('RCT-'.now()->year.'-0001', $payment->receipt_number);
        $this->assertTrue($payment->receivedBy->is($this->bursar));
        $this->assertTrue($payment->academicTerm->is($this->term));
        $this->assertSame(600_000, $student->feeBalance());

        // No editing or deleting; voiding needs a reason and restores the balance
        $this->assertFalse($this->bursar->can('update', $payment));
        $this->assertFalse($this->bursar->can('delete', $payment));

        Livewire::test(ListPayments::class)
            ->callTableAction('void', $payment, data: ['reason' => 'Entered for the wrong student'])
            ->assertHasNoTableActionErrors();

        $payment->refresh();
        $this->assertTrue($payment->isVoided());
        $this->assertTrue($payment->voidedBy->is($this->bursar));
        $this->assertSame(900_000, $student->feeBalance());
        $this->assertFalse($this->bursar->can('void', $payment));
    }

    public function test_the_balances_page_lists_who_owes_and_records_payments(): void
    {
        $this->feeStructure();
        $owes = $this->student($this->s5, Residence::Boarding);
        $cleared = $this->student($this->s1);
        Invoice::billTerm($this->term);
        Payment::create(['school_id' => $this->school->getKey(), 'student_id' => $cleared->getKey(), 'amount' => 900_000, 'method' => 'cash']);

        Livewire::test(FeeBalances::class)
            ->assertCanSeeTableRecords([$owes])
            ->assertCanNotSeeTableRecords([$cleared])
            ->assertSee('Total owed by all students: UGX 1,650,000')
            ->callTableAction('recordPayment', $owes, data: [
                'amount' => 1_000_000,
                'method' => PaymentMethod::Bank->value,
                'reference' => 'Stanbic 0042',
                'paid_on' => now()->toDateString(),
                'academic_term_id' => $this->term->getKey(),
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame(650_000, $owes->feeBalance());
    }

    public function test_receipts_and_statements_print_for_fees_staff_only(): void
    {
        $this->feeStructure();
        $student = $this->student($this->s1);
        Invoice::billTerm($this->term);
        $payment = Payment::create(['school_id' => $this->school->getKey(), 'student_id' => $student->getKey(), 'amount' => 250_000, 'method' => 'cash']);

        $this->get(route('fees.receipt', $payment))
            ->assertOk()
            ->assertSee('OFFICIAL RECEIPT')
            ->assertSee('St Marys College')
            ->assertSee($payment->receipt_number)
            ->assertSee('UGX 250,000')
            ->assertSee('UGX 650,000');

        $this->get(route('fees.statement', $student))
            ->assertOk()
            ->assertSeeInOrder(['900,000', '250,000', '650,000']);

        $payment->void('Test');
        $this->get(route('fees.receipt', $payment))->assertSee('VOID');

        // A teacher, a bursar of another school, and nobody signed in are refused
        $this->actingAs($this->staff(StaffRole::Teacher))->get(route('fees.receipt', $payment))->assertForbidden();
        $this->actingAs($this->staff(StaffRole::Bursar, School::factory()->create()))->get(route('fees.statement', $student))->assertForbidden();
        auth()->logout();
        $this->get(route('fees.receipt', $payment))->assertForbidden();
    }

    public function test_who_can_open_the_fees_screens(): void
    {
        $pages = [
            'balances' => '/school/stmarys/fee-balances',
            'invoices' => '/school/stmarys/invoices',
            'new invoice' => '/school/stmarys/invoices/create',
            'payments' => '/school/stmarys/payments',
            'record payment' => '/school/stmarys/payments/create',
            'fee structure' => '/school/stmarys/fee-structure',
            'terms' => '/school/stmarys/terms',
        ];
        $access = fn (User $user): array => array_map(fn (string $url): bool => $this->actingAs($user)->get($url)->status() === 200, $pages);

        $this->assertNotContains(false, $access($this->bursar));
        $this->assertNotContains(false, $access($this->staff(StaffRole::Admin)));
        $this->assertSame(
            ['balances' => true, 'invoices' => true, 'new invoice' => false, 'payments' => true, 'record payment' => false, 'fee structure' => true, 'terms' => true],
            $access($this->staff(StaffRole::HeadTeacher)),
        );

        foreach ([StaffRole::Teacher, StaffRole::Secretary] as $role) {
            $this->assertSame(
                ['balances' => false, 'invoices' => false, 'new invoice' => false, 'payments' => false, 'record payment' => false, 'fee structure' => false, 'terms' => true],
                $access($this->staff($role)),
                $role->getLabel(),
            );
        }
    }

    public function test_students_with_fees_records_cannot_be_deleted(): void
    {
        $this->feeStructure();
        $student = $this->student($this->s1);
        $fresh = $this->student($this->s1, extra: ['status' => StudentStatus::Left]);
        Invoice::billTerm($this->term);

        $this->signInAs($this->staff(StaffRole::Admin));

        Livewire::test(EditStudent::class, ['record' => $student->getKey()])->assertActionHidden('delete');
        Livewire::test(EditStudent::class, ['record' => $fresh->getKey()])->assertActionVisible('delete');
    }

    public function test_dashboard_shows_this_terms_fees_to_fees_staff_only(): void
    {
        $this->feeStructure();
        $student = $this->student($this->s1);
        Invoice::billTerm($this->term);
        Payment::create(['school_id' => $this->school->getKey(), 'student_id' => $student->getKey(), 'amount' => 450_000, 'method' => 'cash']);

        $this->assertTrue(FeesOverview::canView());
        Livewire::test(FeesOverview::class)
            ->assertSeeInOrder(['Billed this term', 'UGX 900,000', 'Collected this term', 'UGX 450,000', '50%', 'Outstanding', 'UGX 450,000', '1 student owes']);

        $this->signInAs($this->staff(StaffRole::Teacher));
        $this->assertFalse(FeesOverview::canView());
    }

    public function test_one_schools_fees_are_invisible_and_unusable_from_another_school(): void
    {
        $this->feeStructure();
        $mine = $this->student($this->s1);
        Invoice::billTerm($this->term);
        $myPayment = Payment::create(['school_id' => $this->school->getKey(), 'student_id' => $mine->getKey(), 'amount' => 100_000, 'method' => 'cash']);

        // Kings College, with its own term, student, invoice and payment
        Filament::setCurrentPanel(null);
        $kings = School::factory()->create(['slug' => 'kings']);
        $kingsTerm = AcademicTerm::factory()->create(['school_id' => $kings->getKey()]);
        $kingsClass = SchoolClass::factory()->create(['school_id' => $kings->getKey(), 'name' => 'S.1']);
        $kingsStudent = Student::factory()->create(['school_id' => $kings->getKey(), 'school_class_id' => $kingsClass->getKey()]);
        FeeItem::create(['school_id' => $kings->getKey(), 'academic_term_id' => $kingsTerm->getKey(), 'name' => 'Tuition', 'amount' => 500_000]);
        Invoice::billTerm($kingsTerm);
        $kingsInvoice = Invoice::where('school_id', $kings->getKey())->firstOrFail();
        $kingsPayment = Payment::create(['school_id' => $kings->getKey(), 'student_id' => $kingsStudent->getKey(), 'amount' => 200_000, 'method' => 'cash']);
        $this->signInAs($this->bursar);

        // Lists only show this school's records
        Livewire::test(ListInvoices::class)->assertCanSeeTableRecords([$mine->invoices()->first()])->assertCanNotSeeTableRecords([$kingsInvoice]);
        Livewire::test(ListPayments::class)->assertCanSeeTableRecords([$myPayment])->assertCanNotSeeTableRecords([$kingsPayment]);
        Livewire::test(FeeBalances::class)->assertCanNotSeeTableRecords([$kingsStudent]);

        // Opening Kings' records through St Marys' portal fails
        $this->get("/school/stmarys/invoices/{$kingsInvoice->getKey()}")->assertNotFound();
        $this->get("/school/stmarys/payments/{$kingsPayment->getKey()}")->assertNotFound();

        // Kings' receipts and statements can't be printed by St Marys' bursar
        // (these pages sit outside the portal, so the permission check decides)
        Filament::setCurrentPanel(null);
        $this->get(route('fees.receipt', $kingsPayment))->assertForbidden();
        $this->get(route('fees.statement', $kingsStudent))->assertForbidden();
        $this->get(route('fees.receipt', $myPayment))->assertOk();
        $this->signInAs($this->bursar);

        // Kings' students and terms can't be used in St Marys' forms
        Livewire::test(CreatePayment::class)
            ->fillForm(['student_id' => $kingsStudent->getKey(), 'amount' => 1000, 'method' => 'cash', 'academic_term_id' => $kingsTerm->getKey()])
            ->call('create')
            ->assertHasFormErrors(['student_id', 'academic_term_id']);

        Livewire::test(ListInvoices::class)
            ->callAction('billStudents', data: ['academic_term_id' => $kingsTerm->getKey()])
            ->assertHasActionErrors(['academic_term_id']);

        // Nothing was added to either school
        $this->assertSame(1, Payment::withoutGlobalScopes()->where('school_id', $kings->getKey())->count());
        $this->assertSame(1, Invoice::withoutGlobalScopes()->where('school_id', $kings->getKey())->count());
        $this->assertSame(1, Payment::withoutGlobalScopes()->where('school_id', $this->school->getKey())->count());
    }

    public function test_fees_tabs_on_a_student_are_for_fees_staff_and_only_the_bursar_records_payments(): void
    {
        $this->feeStructure();
        $student = $this->student($this->s1);
        Invoice::billTerm($this->term);
        $payment = Payment::create(['school_id' => $this->school->getKey(), 'student_id' => $student->getKey(), 'amount' => 100_000, 'method' => 'cash']);

        $this->assertTrue(PaymentsRelationManager::canViewForRecord($student, ViewStudent::class));
        Livewire::test(PaymentsRelationManager::class, ['ownerRecord' => $student, 'pageClass' => ViewStudent::class])
            ->assertSee('Balance UGX 800,000')
            ->callTableAction('create', data: ['amount' => 50_000, 'method' => 'cash', 'paid_on' => now()->toDateString(), 'academic_term_id' => $this->term->getKey()])
            ->assertHasNoTableActionErrors();
        $this->assertSame(750_000, $student->feeBalance());

        // Head teacher: can see fees, but can't record or void payments
        $this->signInAs($this->staff(StaffRole::HeadTeacher));
        $this->assertTrue(PaymentsRelationManager::canViewForRecord($student, ViewStudent::class));
        Livewire::test(PaymentsRelationManager::class, ['ownerRecord' => $student, 'pageClass' => ViewStudent::class])
            ->assertTableActionHidden('create')
            ->assertTableActionHidden('void', $payment);
        Livewire::test(ListPayments::class)->assertTableActionHidden('void', $payment);
        Livewire::test(FeeBalances::class)->assertTableActionHidden('recordPayment', $student);

        // Teachers and secretaries don't see the fees tabs at all
        foreach ([StaffRole::Teacher, StaffRole::Secretary] as $role) {
            $this->signInAs($this->staff($role));
            $this->assertFalse(PaymentsRelationManager::canViewForRecord($student, ViewStudent::class), $role->getLabel());
            $this->assertFalse(InvoicesRelationManager::canViewForRecord($student, ViewStudent::class), $role->getLabel());
            $this->get("/school/stmarys/students/{$student->getKey()}")->assertOk()->assertDontSee('Fee statement');
        }
    }

    public function test_a_reprinted_receipt_keeps_the_balance_it_had_when_issued(): void
    {
        $this->feeStructure();
        $student = $this->student($this->s1);
        Invoice::billTerm($this->term);
        $first = Payment::create(['school_id' => $this->school->getKey(), 'student_id' => $student->getKey(), 'amount' => 300_000, 'method' => 'cash']);
        $second = Payment::create(['school_id' => $this->school->getKey(), 'student_id' => $student->getKey(), 'amount' => 200_000, 'method' => 'cash']);

        $this->assertSame(600_000, $first->balanceAfter());
        $this->assertSame(400_000, $second->balanceAfter());

        $this->get(route('fees.receipt', $first))
            ->assertSee('Fees balance after this payment: <strong>UGX 600,000</strong>', false)
            ->assertSee('UGX 400,000');

        // Voiding the second payment changes the current balance, not the first receipt
        $second->void('Entered twice');
        $this->assertSame(600_000, $first->fresh()->balanceAfter());
        $this->get(route('fees.receipt', $second))->assertSee('VOID')->assertDontSee('Fees balance after this payment');
    }

    public function test_payments_entered_before_the_term_was_billed_still_count_against_that_terms_bill(): void
    {
        // A school starts using the system mid-term: it records payments received
        // in September, then bills Term 3 in October.
        $this->feeStructure();
        $student = $this->student($this->s1);
        $september = Payment::create(['school_id' => $this->school->getKey(), 'student_id' => $student->getKey(), 'amount' => 500_000, 'method' => 'cash', 'paid_on' => '2026-09-08']);

        $this->travelTo('2026-10-20');
        Invoice::billTerm($this->term);
        $october = Payment::create(['school_id' => $this->school->getKey(), 'student_id' => $student->getKey(), 'amount' => 100_000, 'method' => 'cash', 'paid_on' => '2026-10-20']);

        $this->assertSame(400_000, $september->balanceAfter());
        $this->assertSame(300_000, $october->balanceAfter());

        Filament::setCurrentPanel(null);
        $this->get(route('fees.statement', $student))
            ->assertOk()
            ->assertSeeInOrder(['Invoice INV-2026-0001', '900,000', $september->receipt_number, '400,000', $october->receipt_number, '300,000'])
            ->assertDontSee('-500,000');

        // Next term's bill comes after this term's payments
        $term1 = AcademicTerm::factory()->create(['school_id' => $this->school->getKey(), 'year' => 2027, 'term' => 1, 'is_current' => true]);
        $this->signInAs($this->bursar);
        FeeItem::create(['school_id' => $this->school->getKey(), 'academic_term_id' => $term1->getKey(), 'name' => 'Tuition', 'amount' => 800_000]);
        Invoice::billTerm($term1);

        $this->assertSame(
            ['invoice', 'payment', 'payment', 'invoice'],
            $student->feeLedger()->pluck('type')->all(),
        );
        $this->assertSame(1_100_000, $student->feeLedger()->last()['balance']);
        $this->assertSame(1_100_000, $student->feeBalance());
    }

    public function test_print_links_open_on_the_schools_own_address(): void
    {
        $student = $this->student($this->s1);
        $payment = Payment::create(['school_id' => $this->school->getKey(), 'student_id' => $student->getKey(), 'amount' => 1000, 'method' => 'cash']);

        $this->assertSame("/fees/receipts/{$payment->getKey()}", route('fees.receipt', $payment, absolute: false));

        // The same link works on a school subdomain (live-server mode)
        $this->get("http://stmarys.sms.test/fees/receipts/{$payment->getKey()}")->assertOk()->assertSee($payment->receipt_number);
    }
}
