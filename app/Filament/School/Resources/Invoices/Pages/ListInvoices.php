<?php

namespace App\Filament\School\Resources\Invoices\Pages;

use App\Filament\School\Fields\FeeFields;
use App\Filament\School\Resources\Invoices\InvoiceResource;
use App\Models\AcademicTerm;
use App\Models\Invoice;
use App\Models\SchoolClass;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->billStudentsAction(),
            CreateAction::make()
                ->label('New invoice'),
        ];
    }

    /**
     * Creates an invoice for every active student who doesn't have one for
     * the term yet, filled from the fee structure. Safe to run again, e.g.
     * after admitting new students mid-term.
     */
    protected function billStudentsAction(): Action
    {
        return Action::make('billStudents')
            ->label('Bill students')
            ->icon(Heroicon::OutlinedBanknotes)
            ->authorize('create', Invoice::class)
            ->modalDescription('Creates an invoice for each active student who doesn\'t have one for this term yet, using the fee structure. Students already billed are left as they are.')
            ->modalSubmitActionLabel('Create invoices')
            ->schema([
                FeeFields::term(),
                Select::make('classes')
                    ->label('Only these classes')
                    ->multiple()
                    ->options(fn (): array => SchoolClass::query()
                        ->where('school_id', Filament::getTenant()?->getKey())
                        ->orderBy('sort_order')
                        ->pluck('name', 'id')
                        ->all())
                    ->placeholder('All classes'),
            ])
            ->action(function (array $data): void {
                $term = AcademicTerm::query()->withoutGlobalScopes()
                    ->where('school_id', Filament::getTenant()->getKey())
                    ->findOrFail($data['academic_term_id']);

                if (! $term->feeItems()->withoutGlobalScopes()->exists()) {
                    Notification::make()
                        ->title("No fee structure for {$term->name} yet")
                        ->body('Add the fee items under Fees → Fee structure first.')
                        ->warning()
                        ->send();

                    return;
                }

                $created = Invoice::billTerm($term, array_map('intval', $data['classes'] ?? []));

                Notification::make()
                    ->title($created > 0
                        ? "Created {$created} ".str('invoice')->plural($created)." for {$term->name}"
                        : 'Everyone is already billed for this term')
                    ->success()
                    ->send();
            });
    }
}
