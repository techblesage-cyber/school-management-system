<?php

namespace App\Http\Controllers;

use App\Enums\SchoolAbility;
use App\Models\AcademicTerm;
use App\Models\SchoolClass;
use App\Models\User;
use App\Support\Results\ReportCardPrinter;
use App\Support\Results\StudentResult;
use App\Support\Results\TermResults;
use App\Support\TeacherScope;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Printable report cards for staff: one student, one stream or a whole
 * class. Opens in a new tab, ready for the browser's Print button.
 */
class ReportCardController extends Controller
{
    public function print(Request $request, int $term, int $class): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $term = AcademicTerm::query()->withoutGlobalScopes()->findOrFail($term);
        $class = SchoolClass::query()->withoutGlobalScopes()->where('school_id', $term->school_id)->findOrFail($class);
        $school = $class->school()->firstOrFail();

        abort_unless($user->canInSchool(SchoolAbility::ViewResults, $school), 403);

        $scope = TeacherScope::for($user, $school);
        $streamId = $request->integer('stream') ?: null;
        $studentId = $request->integer('student') ?: null;

        $results = TermResults::forClass($term, $class);
        $students = $results->students
            ->filter(fn (StudentResult $result): bool => $result->hasMarks())
            ->when($streamId, fn ($students) => $students->filter(fn (StudentResult $result): bool => $result->streamId === $streamId))
            ->when($studentId, fn ($students) => $students->filter(fn (StudentResult $result): bool => $result->student->getKey() === $studentId))
            // Teachers only print the students of classes or streams they may see
            ->filter(fn (StudentResult $result): bool => $scope->canSeeClass($class->getKey(), $result->streamId));

        abort_if($students->isEmpty(), 404, 'No report cards to print: no marks have been entered for these students this term.');

        return ReportCardPrinter::render($results, $students);
    }
}
