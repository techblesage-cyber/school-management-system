<?php

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Models\ParentAccount;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Support\ParentPortal;
use App\Support\Results\ReportCardPrinter;
use App\Support\Results\TermResults;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Report cards and fee statements printed from the parents' portal. A
 * parent only reaches their own children, and only published report cards.
 */
class ParentDocumentController extends Controller
{
    public function reportCard(int $term, int $student): View
    {
        $child = $this->child($student);
        $term = AcademicTerm::query()->withoutGlobalScopes()->where('school_id', $child->school_id)->findOrFail($term);

        abort_unless(ParentPortal::result($child, $term) !== null, 404, 'This report card has not been published yet.');

        // Printed with the class the child was in that term, so positions are right
        $class = SchoolClass::query()->withoutGlobalScopes()->findOrFail(TermResults::classOfStudentInTerm($term, $child));
        $results = TermResults::forClass($term, $class);

        return ReportCardPrinter::render($results, collect([$results->students->get($child->getKey())])->filter());
    }

    public function statement(int $student): View
    {
        return FeeDocumentController::statementView($this->child($student));
    }

    protected function child(int $student): Student
    {
        $parent = Auth::guard('parent')->user();
        abort_unless($parent instanceof ParentAccount, 403);

        $child = $parent->childrenQuery()->find($student);
        abort_unless($child instanceof Student, 404);

        return $child;
    }
}
