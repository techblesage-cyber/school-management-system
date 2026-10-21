<?php

namespace App\Filament\School\Widgets;

use App\Enums\SchoolAbility;
use App\Filament\School\Pages\FeeBalances;
use App\Filament\School\Resources\Payments\PaymentResource;
use App\Models\AcademicTerm;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Support\Money;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/**
 * Fees at a glance, for staff who can see fees.
 */
class FeesOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->canInSchool(SchoolAbility::ViewFees);
    }

    protected function getHeading(): ?string
    {
        $term = AcademicTerm::currentFor(Filament::getTenant());

        return $term ? "Fees · {$term->name}" : 'Fees';
    }

    protected function getStats(): array
    {
        /** @var School $school */
        $school = Filament::getTenant();
        $term = AcademicTerm::currentFor($school);

        $billed = $term ? (int) $school->invoices()->where('academic_term_id', $term->getKey())->sum('total') : 0;
        $collected = $term ? (int) $school->payments()->valid()->where('academic_term_id', $term->getKey())->sum('amount') : 0;
        $today = (int) $school->payments()->valid()->whereDate('paid_on', today())->sum('amount');

        $owing = Student::query()
            ->withoutGlobalScopes()
            ->where('school_id', $school->getKey())
            ->whereRaw(Student::feeBalanceSql().' > 0');
        $outstanding = (int) (clone $owing)->sum(DB::raw(Student::feeBalanceSql()));
        $owingCount = (clone $owing)->count();

        $rate = $billed > 0 ? (int) round($collected / $billed * 100) : null;

        return [
            Stat::make('Billed this term', Money::format($billed))
                ->icon(Heroicon::OutlinedDocumentText),
            Stat::make('Collected this term', Money::format($collected))
                ->description($rate !== null ? "{$rate}% of this term's bills" : null)
                ->color('success')
                ->icon(Heroicon::OutlinedBanknotes)
                ->url(PaymentResource::getUrl()),
            Stat::make('Outstanding', Money::format($outstanding))
                ->description($owingCount === 1 ? '1 student owes' : number_format($owingCount).' students owe')
                ->color($outstanding > 0 ? 'danger' : 'success')
                ->icon(Heroicon::OutlinedScale)
                ->url(FeeBalances::getUrl()),
            Stat::make('Received today', Money::format($today))
                ->icon(Heroicon::OutlinedCalendarDays),
        ];
    }
}
