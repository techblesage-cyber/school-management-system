<?php

namespace App\Support;

use App\Enums\ApplicationStatus;
use App\Filament\School\Pages\ClassFees;
use App\Filament\School\Pages\EnterMarks;
use App\Filament\School\Pages\FeeBalances;
use App\Filament\School\Pages\ReportCards;
use App\Filament\School\Pages\Staff;
use App\Filament\School\Pages\TakeAttendance;
use App\Filament\School\Pages\Tenancy\EditSchoolProfile;
use App\Filament\School\Resources\AcademicTerms\AcademicTermResource;
use App\Filament\School\Resources\AdmissionApplications\AdmissionApplicationResource;
use App\Filament\School\Resources\Assessments\AssessmentResource;
use App\Filament\School\Resources\Circulars\CircularResource;
use App\Filament\School\Resources\FeeItems\FeeItemResource;
use App\Filament\School\Resources\Guardians\GuardianResource;
use App\Filament\School\Resources\Invoices\InvoiceResource;
use App\Filament\School\Resources\Payments\PaymentResource;
use App\Filament\School\Resources\SchoolClasses\SchoolClassResource;
use App\Filament\School\Resources\Students\StudentResource;
use App\Filament\School\Resources\Subjects\SubjectResource;
use App\Models\AdmissionApplication;
use App\Models\School;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Throwable;

/**
 * The "apps" on each person's home screen and in the phone tab bar: big,
 * coloured tiles for the jobs they do, in the order they're most often
 * needed. Each person only sees the apps their role can open.
 */
class Apps
{
    /**
     * @return array<int, array{key: string, label: string, icon: Heroicon, color: string, url: string, badge: int|null}>
     */
    public static function forSchool(): array
    {
        $school = Filament::getTenant();

        if (! $school instanceof School) {
            return [];
        }

        $apps = [
            ['attendance', 'Attendance', Heroicon::OutlinedCheckBadge, '#34c759', fn () => TakeAttendance::canAccess(), fn () => TakeAttendance::getUrl()],
            ['marks', 'Enter marks', Heroicon::OutlinedPencilSquare, '#ff9500', fn () => EnterMarks::canAccess(), fn () => EnterMarks::getUrl()],
            ['pay', 'Record payment', Heroicon::OutlinedBanknotes, '#00c7be', fn () => PaymentResource::canCreate(), fn () => PaymentResource::getUrl('create')],
            ['class-fees', 'Fees by class', Heroicon::OutlinedSquares2x2, '#30b0c7', fn () => ClassFees::canAccess(), fn () => ClassFees::getUrl()],
            ['students', 'Students', Heroicon::OutlinedAcademicCap, '#007aff', fn () => StudentResource::canAccess(), fn () => StudentResource::getUrl()],
            ['admissions', 'Admissions', Heroicon::OutlinedClipboardDocumentList, '#5856d6', fn () => AdmissionApplicationResource::canAccess(), fn () => AdmissionApplicationResource::getUrl()],
            ['parents', 'Parents', Heroicon::OutlinedUsers, '#af52de', fn () => GuardianResource::canAccess(), fn () => GuardianResource::getUrl()],
            ['report-cards', 'Report cards', Heroicon::OutlinedDocumentChartBar, '#ff2d55', fn () => ReportCards::canAccess(), fn () => ReportCards::getUrl()],
            ['circulars', 'Circulars', Heroicon::OutlinedMegaphone, '#ff3b30', fn () => CircularResource::canAccess(), fn () => CircularResource::getUrl()],
            ['exams', 'Exams', Heroicon::OutlinedClipboardDocumentCheck, '#ff9f0a', fn () => AssessmentResource::canAccess(), fn () => AssessmentResource::getUrl()],
            ['balances', 'Fee balances', Heroicon::OutlinedScale, '#34c759', fn () => FeeBalances::canAccess(), fn () => FeeBalances::getUrl()],
            ['invoices', 'Invoices', Heroicon::OutlinedDocumentText, '#5ac8fa', fn () => InvoiceResource::canAccess(), fn () => InvoiceResource::getUrl()],
            ['fee-structure', 'Fee structure', Heroicon::OutlinedTableCells, '#32ade6', fn () => FeeItemResource::canCreate(), fn () => FeeItemResource::getUrl()],
            ['staff', 'Staff', Heroicon::OutlinedUserGroup, '#8e8e93', fn () => Staff::canAccess(), fn () => Staff::getUrl()],
            ['classes', 'Classes', Heroicon::OutlinedRectangleGroup, '#5856d6', fn () => SchoolClassResource::canCreate(), fn () => SchoolClassResource::getUrl()],
            ['subjects', 'Subjects', Heroicon::OutlinedBookOpen, '#ff9500', fn () => SubjectResource::canCreate(), fn () => SubjectResource::getUrl()],
            ['terms', 'Terms', Heroicon::OutlinedCalendarDays, '#ff2d55', fn () => AcademicTermResource::canCreate(), fn () => AcademicTermResource::getUrl()],
            ['profile', 'School profile', Heroicon::OutlinedBuildingLibrary, '#a2845e', fn () => EditSchoolProfile::canView($school), fn () => Filament::getTenantProfileUrl()],
        ];

        $badges = [
            'admissions' => fn (): int => AdmissionApplication::query()->withoutGlobalScopes()
                ->where('school_id', $school->getKey())
                ->where('status', ApplicationStatus::Pending)
                ->count(),
        ];

        $visible = [];

        foreach ($apps as [$key, $label, $icon, $color, $allowed, $url]) {
            try {
                if (! $allowed()) {
                    continue;
                }

                $visible[] = [
                    'key' => $key,
                    'label' => $label,
                    'icon' => $icon,
                    'color' => $color,
                    'url' => $url(),
                    'badge' => isset($badges[$key]) ? ($badges[$key]() ?: null) : null,
                ];
            } catch (Throwable $exception) {
                // An app that can't be worked out is simply left off the screen
                report($exception);
            }
        }

        return $visible;
    }
}
