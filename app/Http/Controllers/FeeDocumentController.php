<?php

namespace App\Http\Controllers;

use App\Enums\SchoolAbility;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Printable fees documents: payment receipts and student fee statements.
 * They open in a new tab, where the browser's Print button prints them.
 */
class FeeDocumentController extends Controller
{
    public function receipt(Request $request, Payment $payment): View
    {
        abort_unless($request->user(), 403);
        Gate::authorize('view', $payment);

        $payment->load(['student.schoolClass', 'student.stream', 'student.school', 'academicTerm', 'receivedBy']);

        return view('fees.receipt', [
            'payment' => $payment,
            'school' => $payment->student->school,
            'student' => $payment->student,
            'balanceAfter' => $payment->balanceAfter(),
            'balanceNow' => $payment->student->feeBalance(),
            'inWords' => Money::inWords($payment->amount),
        ]);
    }

    public function statement(Request $request, Student $student): View
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->canInSchool(SchoolAbility::ViewFees, $student->school_id), 403);

        return static::statementView($student);
    }

    /**
     * A student's fee statement, for staff and (from the parents' portal)
     * for their parents.
     */
    public static function statementView(Student $student): View
    {
        $student->load(['schoolClass', 'stream', 'school', 'guardians']);

        $lines = $student->feeLedger();

        return view('fees.statement', [
            'student' => $student,
            'school' => $student->school,
            'lines' => $lines,
            'totals' => [
                'billed' => (int) $lines->sum('debit'),
                'paid' => (int) $lines->sum('credit'),
                'balance' => (int) ($lines->last()['balance'] ?? 0),
            ],
        ]);
    }
}
