<?php

namespace App\Services\Sms;

use App\Enums\SmsStatus;
use App\Jobs\SendSmsMessage;
use App\Models\Circular;
use App\Models\Guardian;
use App\Models\ParentAccount;
use App\Models\Payment;
use App\Models\School;
use App\Models\SmsMessage;
use App\Models\Student;
use App\Support\Money;
use App\Support\PhoneNumber;
use App\Support\SchoolAddress;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Text messages to parents. Nothing is sent unless SMS is switched on for
 * the school (by Sharebility, in the admin panel). Messages are logged in
 * sms_messages and sent in the background.
 */
class Sms
{
    public static function enabledFor(?School $school): bool
    {
        return (bool) $school?->sms_enabled;
    }

    /**
     * Queues one message. Returns null when SMS is off or the number is missing.
     */
    public static function send(School $school, ?string $phone, string $message, string $purpose): ?SmsMessage
    {
        $to = PhoneNumber::normalize($phone);

        if (! static::enabledFor($school) || blank($to)) {
            return null;
        }

        $sms = SmsMessage::query()->withoutGlobalScopes()->create([
            'school_id' => $school->getKey(),
            'to' => $to,
            'message' => Str::limit(trim($message), 459, '…'),
            'purpose' => $purpose,
            'status' => SmsStatus::Queued,
            'sent_by' => auth()->guard('web')->id(),
        ]);

        SendSmsMessage::dispatch($sms)->afterCommit();

        return $sms;
    }

    /**
     * The parent to text about a student: the main contact, otherwise the
     * first parent or guardian with a phone number.
     */
    public static function parentOf(Student $student): ?Guardian
    {
        $guardians = $student->guardians()->withoutGlobalScopes()->get()->filter(fn (Guardian $guardian): bool => filled($guardian->phone));

        return $guardians->first(fn (Guardian $guardian): bool => (bool) $guardian->pivot?->is_primary) ?? $guardians->first();
    }

    /**
     * "Received UGX 350,000 for Okello Brian (2025/0001), receipt RCT-2026-0002."
     */
    public static function paymentReceipt(Payment $payment): ?SmsMessage
    {
        $student = Student::query()->withoutGlobalScopes()->with('school')->find($payment->student_id);
        $school = $student?->school;

        if (! $student || ! $school?->sms_payment_receipts || $payment->isVoided()) {
            return null;
        }

        return static::send(
            $school,
            static::parentOf($student)?->phone,
            static::schoolName($school).': Received '.Money::format($payment->amount)." for {$student->full_name} ({$student->admission_number}), receipt {$payment->receipt_number}. Balance ".Money::balance($payment->balanceAfter() ?? $student->feeBalance()).'. Thank you.',
            'payment_receipt',
        );
    }

    public static function feeReminder(Student $student): ?SmsMessage
    {
        $balance = $student->feeBalance();
        $school = $student->school()->first();

        if ($balance <= 0 || ! $school) {
            return null;
        }

        return static::send(
            $school,
            static::parentOf($student)?->phone,
            static::schoolName($school).": Dear parent, the fees balance for {$student->full_name} ({$student->admission_number}) is ".Money::format($balance).'. Please clear it at your earliest convenience. Thank you.',
            'fee_reminder',
        );
    }

    /**
     * Tells the parents a circular is for. Each phone number gets it once.
     */
    public static function circular(Circular $circular): int
    {
        $school = $circular->school()->first();

        if (! static::enabledFor($school) || ! $circular->isPublished() || ! $circular->audience->includesParents()) {
            return 0;
        }

        $classIds = $circular->classes()->pluck('school_classes.id');

        $students = Student::query()->withoutGlobalScopes()
            ->where('school_id', $school->getKey())
            ->where('status', 'active')
            ->when($classIds->isNotEmpty(), fn ($query) => $query->whereIn('school_class_id', $classIds))
            ->get();

        $message = static::schoolName($school).': '.Str::limit($circular->title, 60).'. '.Str::limit($circular->excerpt(200), 110).' Read it in the parents\' portal: '.SchoolAddress::parentPortal($school);

        return static::toParents($school, $students, fn () => $message, 'circular');
    }

    /**
     * Tells parents their children's report cards are ready.
     *
     * @param  iterable<Student>  $students
     */
    public static function reportCardsReady(School $school, iterable $students, string $termName): int
    {
        return static::toParents(
            $school,
            collect($students),
            fn (Student $student): string => static::schoolName($school).": {$student->first_name}'s report card for {$termName} is ready. See it in the parents' portal: ".SchoolAddress::parentPortal($school),
            'report_card',
        );
    }

    /**
     * Sends a new portal password by SMS, and says so in the notification.
     */
    public static function portalLogin(Guardian $guardian, ParentAccount $account, string $password, ?Notification $notification = null): ?SmsMessage
    {
        $school = $guardian->school()->first();

        $sms = static::send(
            $school,
            $account->phone,
            static::schoolName($school).": Your parents' portal login. Phone: ".PhoneNumber::display($account->phone).", password: {$password}. Sign in at ".SchoolAddress::parentPortal($school).' and choose your own password.',
            'portal_login',
        );

        if ($sms && $notification) {
            $notification->body($notification->getBody().' Also sent to them by SMS.');
        }

        return $sms;
    }

    /**
     * @param  Collection<int, Student>  $students
     * @param  callable(Student): string  $message
     */
    protected static function toParents(School $school, Collection $students, callable $message, string $purpose): int
    {
        if (! static::enabledFor($school)) {
            return 0;
        }

        $sent = [];

        foreach ($students as $student) {
            $phone = PhoneNumber::normalize(static::parentOf($student)?->phone);

            if ($phone && ! isset($sent[$phone]) && static::send($school, $phone, $message($student), $purpose)) {
                $sent[$phone] = true;
            }
        }

        return count($sent);
    }

    protected static function schoolName(School $school): string
    {
        return Str::limit($school->name, 30, '');
    }
}
