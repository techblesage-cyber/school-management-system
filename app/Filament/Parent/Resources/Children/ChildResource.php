<?php

namespace App\Filament\Parent\Resources\Children;

use App\Filament\Parent\Resources\Children\Pages\ListChildren;
use App\Filament\Parent\Resources\Children\Pages\ViewChild;
use App\Filament\School\Pages\ReportCards;
use App\Models\AcademicTerm;
use App\Models\ParentAccount;
use App\Models\Student;
use App\Support\Money;
use App\Support\ParentPortal;
use App\Support\Results\TermResults;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * "My children": each child's results, report cards, fees and attendance.
 * Only the signed-in parent's own children are ever listed or opened.
 */
class ChildResource extends Resource
{
    protected static ?string $model = Student::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?string $navigationLabel = 'My children';

    protected static ?string $modelLabel = 'child';

    protected static ?string $pluralModelLabel = 'my children';

    protected static ?string $slug = 'children';

    protected static ?int $navigationSort = 1;

    // Parents aren't staff, so staff permission rules don't apply here:
    // access is limited to their own children by the query below.
    protected static bool $shouldSkipAuthorization = true;

    public static function getEloquentQuery(): Builder
    {
        $parent = Filament::auth()->user();

        return $parent instanceof ParentAccount
            ? $parent->childrenQuery()->with(['school', 'schoolClass', 'stream'])
            : Student::query()->withoutGlobalScopes()->whereRaw('1 = 0');
    }

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record instanceof Student ? $record->full_name : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo')
                    ->disk('public')
                    ->circular()
                    ->visibleFrom('sm'),
                TextColumn::make('last_name')
                    ->label('Child')
                    ->formatStateUsing(fn (Student $record): string => $record->full_name)
                    ->description(fn (Student $record): string => trim($record->school->name.' · '.$record->class_and_stream, ' ·'))
                    ->weight('bold')
                    ->wrap(),
                TextColumn::make('latest_result')
                    ->label('Latest result')
                    ->state(fn (Student $record): ?string => ($latest = ParentPortal::latest($record))
                        ? $latest[0]->name.': '.ReportCards::summaryLine($latest[1])
                        : null)
                    ->placeholder('No report card yet')
                    ->visibleFrom('md'),
                TextColumn::make('fees')
                    ->label('Fees balance')
                    ->state(fn (Student $record): string => Money::balance($record->feeBalance()))
                    ->color(fn (Student $record): string => $record->feeBalance() > 0 ? 'danger' : 'success'),
            ])
            ->recordUrl(fn (Student $record): string => static::getUrl('view', ['record' => $record]))
            ->paginated(false)
            ->emptyStateHeading('No children linked to your account')
            ->emptyStateDescription('Ask the school to link your children to your phone number.');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columnSpanFull()
                    ->columns(['default' => 1, 'sm' => 3])
                    ->schema([
                        ImageEntry::make('photo')
                            ->hiddenLabel()
                            ->disk('public')
                            ->imageHeight(140)
                            ->visible(fn (Student $record): bool => filled($record->photo)),
                        Grid::make()
                            ->columnSpan(['sm' => 2])
                            ->columns(['default' => 1, 'sm' => 2])
                            ->schema([
                                TextEntry::make('school.name')->label('School'),
                                TextEntry::make('class_and_stream')->label('Class')->placeholder('—'),
                                TextEntry::make('admission_number')->label('Admission number'),
                                TextEntry::make('residence'),
                                TextEntry::make('attendance')
                                    ->label('Attendance this term')
                                    ->state(fn (Student $record): ?string => ($a = ParentPortal::attendanceThisTerm($record))
                                        ? "{$a['present']} days present · {$a['absent']} absent".($a['late'] ? " · late {$a['late']} times" : '')
                                        : null)
                                    ->placeholder('Not recorded yet'),
                                TextEntry::make('fees')
                                    ->label('Fees balance')
                                    ->state(fn (Student $record): string => Money::balance($record->feeBalance()))
                                    ->color(fn (Student $record): string => $record->feeBalance() > 0 ? 'danger' : 'success')
                                    ->weight('bold')
                                    ->suffixAction(
                                        Action::make('statement')
                                            ->label('Fee statement')
                                            ->icon(Heroicon::OutlinedPrinter)
                                            ->url(fn (Student $record): string => route('parents.statement', $record, absolute: false))
                                            ->openUrlInNewTab(),
                                    ),
                            ]),
                    ]),
                Section::make('Report cards')
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('report_cards')
                            ->hiddenLabel()
                            ->state(fn (Student $record): array => ParentPortal::publishedTerms($record)
                                ->map(fn (AcademicTerm $term): ?array => ($result = ParentPortal::result($record, $term)) ? [
                                    'term' => $term->name,
                                    'summary' => ReportCards::summaryLine($result),
                                    'position' => $record->school->show_positions && $result->position ? "{$result->position} of {$result->outOf}" : null,
                                    'url' => route('parents.report-card', ['term' => $term->getKey(), 'student' => $record->getKey()], absolute: false),
                                ] : null)
                                ->filter()
                                ->values()
                                ->all())
                            ->schema([
                                TextEntry::make('term')->hiddenLabel()->weight('bold'),
                                TextEntry::make('summary')->hiddenLabel(),
                                TextEntry::make('position')->hiddenLabel()->prefix('Position ')->placeholder(''),
                                TextEntry::make('url')
                                    ->hiddenLabel()
                                    ->formatStateUsing(fn (): string => 'Open report card')
                                    ->url(fn (?string $state): ?string => $state)
                                    ->openUrlInNewTab()
                                    ->icon(Heroicon::OutlinedDocumentText)
                                    ->color('primary'),
                            ])
                            ->columns(['default' => 1, 'sm' => 4])
                            ->placeholder('No report cards yet. They appear here once the school publishes them.'),
                    ]),
                Section::make('Latest marks')
                    ->columnSpanFull()
                    ->visible(fn (Student $record): bool => ParentPortal::latest($record) !== null)
                    ->description(fn (Student $record): ?string => ParentPortal::latest($record)[0]->name ?? null)
                    ->schema([
                        RepeatableEntry::make('latest_subjects')
                            ->hiddenLabel()
                            ->state(fn (Student $record): array => collect(ParentPortal::latest($record)[1]->subjects ?? [])
                                ->map(fn ($subject): array => [
                                    'subject' => $subject->subject->name,
                                    'mark' => $subject->final !== null ? TermResults::number($subject->final).'%' : '—',
                                    'grade' => trim(($subject->grade ?? '').' '.($subject->remark ? '· '.$subject->remark : '')),
                                ])
                                ->all())
                            ->schema([
                                // On phones: the subject on its own line, mark and grade under it
                                TextEntry::make('subject')->hiddenLabel()->weight('bold')->columnSpan(['default' => 2, 'sm' => 1]),
                                TextEntry::make('mark')->hiddenLabel(),
                                TextEntry::make('grade')->hiddenLabel()->placeholder(''),
                            ])
                            ->columns(['default' => 2, 'sm' => 3]),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListChildren::route('/'),
            'view' => ViewChild::route('/{record}'),
        ];
    }
}
