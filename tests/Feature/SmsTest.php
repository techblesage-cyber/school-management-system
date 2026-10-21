<?php

namespace Tests\Feature;

use App\Enums\CircularAudience;
use App\Enums\SmsStatus;
use App\Enums\StaffRole;
use App\Filament\School\Pages\FeeBalances;
use App\Filament\School\Pages\ReportCards;
use App\Filament\School\Resources\Circulars\Pages\ListCirculars;
use App\Filament\School\Resources\Guardians\Pages\ListGuardians;
use App\Models\AcademicTerm;
use App\Models\Assessment;
use App\Models\Circular;
use App\Models\FeeItem;
use App\Models\GradingScale;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\Mark;
use App\Models\Payment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SmsMessage;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\Sms\AfricasTalkingDriver;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class SmsTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private AcademicTerm $term;

    private SchoolClass $s1;

    private Student $amina;

    private Guardian $mother;

    private User $bursar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::factory()->create(['slug' => 'stmarys', 'name' => 'St Marys College', 'sms_enabled' => true]);
        $this->term = AcademicTerm::factory()->create(['school_id' => $this->school->getKey()]);
        $this->s1 = SchoolClass::factory()->create(['school_id' => $this->school->getKey(), 'name' => 'S.1']);
        $this->amina = Student::factory()->create(['school_id' => $this->school->getKey(), 'school_class_id' => $this->s1->getKey(), 'first_name' => 'Amina', 'last_name' => 'Nakato']);
        $this->mother = Guardian::factory()->create(['school_id' => $this->school->getKey(), 'phone' => '0772 123 456']);
        $father = Guardian::factory()->create(['school_id' => $this->school->getKey(), 'phone' => '0701 999 888']);
        $this->amina->guardians()->attach($father, ['relationship' => 'father', 'is_primary' => false]);
        $this->amina->guardians()->attach($this->mother, ['relationship' => 'mother', 'is_primary' => true]);
        FeeItem::create(['school_id' => $this->school->getKey(), 'academic_term_id' => $this->term->getKey(), 'name' => 'Tuition', 'amount' => 900_000]);
        Invoice::billTerm($this->term);

        $this->bursar = $this->staff(StaffRole::Bursar);
        $this->signInAs($this->bursar);
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

    private function pay(int $amount): Payment
    {
        return Payment::create(['school_id' => $this->school->getKey(), 'student_id' => $this->amina->getKey(), 'amount' => $amount, 'method' => 'cash']);
    }

    public function test_payments_text_the_main_parent_a_receipt(): void
    {
        $payment = $this->pay(300_000);

        $sms = SmsMessage::sole();
        $this->assertSame('+256772123456', $sms->to);
        $this->assertSame('payment_receipt', $sms->purpose);
        $this->assertSame(SmsStatus::Sent, $sms->status, 'the log driver "sends" straight away in tests');
        $this->assertStringContainsString('Received UGX 300,000 for Nakato Amina', $sms->message);
        $this->assertStringContainsString($payment->receipt_number, $sms->message);
        $this->assertStringContainsString('Balance UGX 600,000', $sms->message);
        $this->assertSame(1, $sms->parts());
    }

    public function test_nothing_is_sent_unless_the_school_uses_sms(): void
    {
        $this->school->update(['sms_payment_receipts' => false]);
        $this->pay(1000);
        $this->assertSame(0, SmsMessage::count());

        $this->school->update(['sms_payment_receipts' => true, 'sms_enabled' => false]);
        $this->pay(1000);
        $this->assertSame(0, SmsMessage::count());

        $this->get('/school/stmarys/fee-balances')->assertOk();
        Livewire::test(FeeBalances::class)->assertTableActionHidden('reminder', $this->amina);
    }

    public function test_fee_reminders_go_to_parents_of_students_who_owe(): void
    {
        $paidUp = Student::factory()->create(['school_id' => $this->school->getKey(), 'school_class_id' => $this->s1->getKey()]);
        $paidUp->guardians()->attach(Guardian::factory()->create(['school_id' => $this->school->getKey(), 'phone' => '0755 000 111']), ['relationship' => 'father', 'is_primary' => true]);
        Payment::create(['school_id' => $this->school->getKey(), 'student_id' => $paidUp->getKey(), 'amount' => 900_000, 'method' => 'cash']);
        SmsMessage::query()->delete();

        Livewire::test(FeeBalances::class)
            ->set('tableFilters.owing.isActive', false)
            ->callTableBulkAction('sendReminders', [$this->amina, $paidUp])
            ->assertNotified('Sending 1 reminder');

        $sms = SmsMessage::sole();
        $this->assertSame('fee_reminder', $sms->purpose);
        $this->assertStringContainsString('UGX 900,000', $sms->message);

        // Head teachers can see fees but not text parents about them
        $this->signInAs($this->staff(StaffRole::HeadTeacher));
        Livewire::test(FeeBalances::class)->assertTableActionHidden('reminder', $this->amina)->assertTableBulkActionHidden('sendReminders');
    }

    public function test_circulars_are_texted_once_per_parent_in_the_classes_they_are_for(): void
    {
        $s2 = SchoolClass::factory()->create(['school_id' => $this->school->getKey(), 'name' => 'S.2']);
        $sibling = Student::factory()->create(['school_id' => $this->school->getKey(), 'school_class_id' => $this->s1->getKey()]);
        $sibling->guardians()->attach($this->mother, ['relationship' => 'mother', 'is_primary' => true]);
        $s2Child = Student::factory()->create(['school_id' => $this->school->getKey(), 'school_class_id' => $s2->getKey()]);
        $s2Child->guardians()->attach(Guardian::factory()->create(['school_id' => $this->school->getKey(), 'phone' => '0788 111 222']), ['relationship' => 'father', 'is_primary' => true]);

        $circular = Circular::create(['school_id' => $this->school->getKey(), 'title' => 'S.1 science trip', 'body' => '<p>Pay UGX 20,000 by Friday.</p>', 'audience' => CircularAudience::Parents, 'published_at' => now()->subMinute()]);
        $circular->classes()->attach($this->s1);
        $draft = Circular::create(['school_id' => $this->school->getKey(), 'title' => 'Draft', 'body' => 'x', 'audience' => CircularAudience::Parents]);

        $this->signInAs($this->staff(StaffRole::Secretary));
        Livewire::test(ListCirculars::class)
            ->assertTableActionHidden('sendSms', $draft)
            ->callTableAction('sendSms', $circular)
            ->assertNotified('Sending to 1 parent');

        $sms = SmsMessage::where('purpose', 'circular')->sole();
        $this->assertSame('+256772123456', $sms->to);
        $this->assertStringContainsString('S.1 science trip', $sms->message);
        $this->assertStringContainsString(url('/parent'), $sms->message);
    }

    public function test_publishing_report_cards_can_tell_parents(): void
    {
        $scale = GradingScale::addStandard($this->school, 'O-level (A–E)');
        $this->s1->update(['grading_scale_id' => $scale->getKey()]);
        $exam = Assessment::create(['school_id' => $this->school->getKey(), 'academic_term_id' => $this->term->getKey(), 'name' => 'End of term', 'max_score' => 100, 'weight' => 100]);
        $subject = Subject::factory()->create(['school_id' => $this->school->getKey()]);
        Mark::create(['school_id' => $this->school->getKey(), 'assessment_id' => $exam->getKey(), 'student_id' => $this->amina->getKey(), 'subject_id' => $subject->getKey(), 'school_class_id' => $this->s1->getKey(), 'score' => 77]);

        $this->signInAs($this->staff(StaffRole::HeadTeacher));
        Livewire::test(ReportCards::class)
            ->set('selection.academic_term_id', $this->term->getKey())
            ->set('selection.class_key', 'c:'.$this->s1->getKey())
            ->callAction('publish', data: ['notify' => true])
            ->assertNotified();

        $sms = SmsMessage::where('purpose', 'report_card')->sole();
        $this->assertStringContainsString("Amina's report card for Term 3, 2026 is ready", $sms->message);
    }

    public function test_portal_logins_are_texted_to_the_parent(): void
    {
        $this->signInAs($this->staff(StaffRole::Secretary));

        Livewire::test(ListGuardians::class)->callTableAction('givePortalAccess', $this->mother);

        $sms = SmsMessage::where('purpose', 'portal_login')->sole();
        $this->assertSame('+256772123456', $sms->to);
        $this->assertMatchesRegularExpression('/password: [a-z2-9]{4}-[a-z2-9]{4}/', $sms->message);
    }

    public function test_africas_talking_sends_and_records_the_result(): void
    {
        Http::fake([
            'api.sandbox.africastalking.com/*' => Http::response(['SMSMessageData' => [
                'Message' => 'Sent to 1/1 Total Cost: UGX 25.0000',
                'Recipients' => [['statusCode' => 101, 'number' => '+256772123456', 'status' => 'Success', 'cost' => 'UGX 25.0000', 'messageId' => 'ATXid_abc123']],
            ]], 201),
        ]);

        $result = (new AfricasTalkingDriver('sandbox', 'test-key', 'SHAREBILITY'))->send('+256772123456', 'Hello');

        $this->assertTrue($result->sent);
        $this->assertSame('ATXid_abc123', $result->providerId);
        $this->assertSame('UGX 25.0000', $result->cost);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.sandbox.africastalking.com/version1/messaging'
            && $request->hasHeader('apiKey', 'test-key')
            && $request['username'] === 'sandbox'
            && $request['to'] === '+256772123456'
            && $request['message'] === 'Hello'
            && $request['from'] === 'SHAREBILITY');

        $this->assertSame('https://api.africastalking.com/version1/messaging', (new AfricasTalkingDriver('sharebility', 'k'))->endpoint());
    }

    public function test_africas_talking_failures_are_recorded(): void
    {
        Http::fake([
            '*' => Http::sequence()
                ->push(['SMSMessageData' => ['Message' => 'Sent to 0/1', 'Recipients' => [['statusCode' => 403, 'number' => '+2567', 'status' => 'InvalidPhoneNumber', 'cost' => '0']]]], 201)
                ->push('Unauthorized', 401),
        ]);

        $driver = new AfricasTalkingDriver('sandbox', 'test-key');

        $rejected = $driver->send('+2567', 'Hello');
        $this->assertFalse($rejected->sent);
        $this->assertSame('InvalidPhoneNumber', $rejected->error);

        $unauthorised = $driver->send('+256772123456', 'Hello');
        $this->assertFalse($unauthorised->sent);
        $this->assertStringContainsString('401', $unauthorised->error);

        $this->assertFalse((new AfricasTalkingDriver('sandbox', null))->send('+256772123456', 'Hello')->sent);
    }

    public function test_the_sms_log_is_for_the_school_admin_and_sharebility(): void
    {
        $this->pay(1000);

        $this->signInAs($this->staff(StaffRole::Admin));
        $this->get('/school/stmarys/sms')->assertOk()->assertSee('0772 123 456');

        foreach ([StaffRole::Bursar, StaffRole::Teacher] as $role) {
            $this->actingAs($this->staff($role))->get('/school/stmarys/sms')->assertForbidden();
        }

        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->superAdmin()->create())->get('/admin/sms')->assertOk()->assertSee('St Marys College');
    }
}
