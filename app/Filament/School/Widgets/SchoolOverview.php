<?php

namespace App\Filament\School\Widgets;

use App\Enums\ApplicationStatus;
use App\Enums\Gender;
use App\Enums\Residence;
use App\Enums\StudentStatus;
use App\Filament\School\Resources\AdmissionApplications\AdmissionApplicationResource;
use App\Filament\School\Resources\Students\StudentResource;
use App\Models\AttendanceRecord;
use App\Models\School;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Headline numbers on the school portal's dashboard.
 */
class SchoolOverview extends StatsOverviewWidget
{
    protected static ?int $sort = -1;

    protected function getStats(): array
    {
        /** @var School $school */
        $school = Filament::getTenant();

        $active = $school->students()->where('status', StudentStatus::Active);
        $total = (clone $active)->count();
        $girls = (clone $active)->where('gender', Gender::Female)->count();
        $boarders = (clone $active)->where('residence', Residence::Boarding)->count();

        $stats = [
            Stat::make('Students', number_format($total))
                ->description(number_format($total - $girls).' '.str('boy')->plural($total - $girls).' · '.number_format($girls).' '.str('girl')->plural($girls))
                ->icon(Heroicon::OutlinedAcademicCap)
                ->url(StudentResource::getUrl()),
            Stat::make('Boarders', number_format($boarders))
                ->description(number_format($total - $boarders).' '.str('day scholar')->plural($total - $boarders))
                ->icon(Heroicon::OutlinedHome),
        ];

        // Today's register, once any class has been marked
        $today = AttendanceRecord::query()->withoutGlobalScopes()
            ->where('school_id', $school->getKey())
            ->whereDate('date', today())
            ->get(['status']);

        if ($today->isNotEmpty()) {
            $atSchool = $today->filter(fn (AttendanceRecord $record): bool => $record->status->isAtSchool())->count();

            $stats[] = Stat::make('At school today', number_format($atSchool))
                ->description(number_format($today->count() - $atSchool).' absent · '.number_format(max(0, $total - $today->count())).' not marked')
                ->icon(Heroicon::OutlinedCheckBadge);
        }

        // Only for staff who handle admissions
        if (AdmissionApplicationResource::canAccess()) {
            $pending = $school->admissionApplications()->where('status', ApplicationStatus::Pending)->count();

            $stats[] = Stat::make('Pending applications', number_format($pending))
                ->description($pending > 0 ? 'Waiting for a decision' : 'All applications handled')
                ->color($pending > 0 ? 'warning' : 'success')
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->url(AdmissionApplicationResource::getUrl());
        }

        return $stats;
    }
}
