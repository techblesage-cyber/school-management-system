<?php

namespace App\Filament\School\Fields;

use App\Enums\PaymentMethod;
use App\Models\AcademicTerm;
use App\Models\Student;
use App\Support\Money;
use Closure;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;

/**
 * Form fields shared by the fees screens.
 */
class FeeFields
{
    /**
     * Search this school's students by name or admission number.
     */
    public static function student(string $name = 'student_id'): Select
    {
        return Select::make($name)
            ->label('Student')
            ->searchable()
            ->getSearchResultsUsing(fn (string $search): array => self::studentsQuery()
                ->where(fn (Builder $query) => $query
                    ->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('other_names', 'like', "%{$search}%")
                    ->orWhere('admission_number', 'like', "%{$search}%"))
                ->with(['schoolClass', 'stream'])
                ->orderBy('last_name')
                ->limit(30)
                ->get()
                ->mapWithKeys(fn (Student $student): array => [$student->getKey() => self::studentLabel($student)])
                ->all())
            ->getOptionLabelUsing(fn ($value): ?string => ($student = self::studentsQuery()->with(['schoolClass', 'stream'])->find($value))
                ? self::studentLabel($student)
                : null)
            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                if (filled($value) && ! self::studentsQuery()->whereKey($value)->exists()) {
                    $fail('Choose one of this school\'s students.');
                }
            })
            ->required();
    }

    public static function term(string $name = 'academic_term_id'): Select
    {
        return Select::make($name)
            ->label('Term')
            ->options(fn (): array => AcademicTerm::optionsFor(Filament::getTenant()))
            ->default(fn (): ?int => AcademicTerm::currentFor(Filament::getTenant())?->getKey())
            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                $belongs = AcademicTerm::query()->withoutGlobalScopes()
                    ->whereKey($value)
                    ->where('school_id', Filament::getTenant()?->getKey())
                    ->exists();

                if (filled($value) && ! $belongs) {
                    $fail('Choose one of this school\'s terms.');
                }
            })
            ->required();
    }

    public static function amount(string $name = 'amount'): TextInput
    {
        return TextInput::make($name)
            ->label('Amount')
            ->prefix(Money::currency())
            ->integer()
            ->required();
    }

    /**
     * The fields for recording money received.
     *
     * @return array<int, mixed>
     */
    public static function paymentFields(bool $withStudent = true): array
    {
        return array_values(array_filter([
            $withStudent
                ? self::student()
                    ->live()
                    ->helperText(fn (Get $get): ?string => ($student = self::studentsQuery()->find($get('student_id')))
                        ? 'Balance before this payment: '.Money::balance($student->feeBalance())
                        : null)
                : null,
            self::amount()
                ->minValue(1)
                ->helperText('Whole shillings, without commas.'),
            DatePicker::make('paid_on')
                ->label('Date paid')
                ->default(now())
                ->maxDate(now())
                ->required(),
            Select::make('method')
                ->label('Paid by')
                ->options(PaymentMethod::class)
                ->default(PaymentMethod::Cash->value)
                ->required()
                ->live(),
            TextInput::make('reference')
                ->label('Slip / transaction number')
                ->maxLength(100)
                ->required(fn (Get $get): bool => self::method($get('method'))?->needsReference() ?? false)
                ->helperText('Bank slip, mobile money or SchoolPay transaction ID.'),
            self::term(),
            Textarea::make('notes')
                ->rows(2),
        ]));
    }

    public static function method(mixed $value): ?PaymentMethod
    {
        return $value instanceof PaymentMethod ? $value : PaymentMethod::tryFrom((string) $value);
    }

    public static function studentLabel(Student $student): string
    {
        return trim("{$student->full_name} · {$student->admission_number} · {$student->class_and_stream}", ' ·');
    }

    protected static function studentsQuery(): Builder
    {
        return Student::query()
            ->withoutGlobalScopes()
            ->where('school_id', Filament::getTenant()?->getKey());
    }
}
