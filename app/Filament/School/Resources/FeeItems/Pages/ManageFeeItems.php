<?php

namespace App\Filament\School\Resources\FeeItems\Pages;

use App\Filament\School\Fields\FeeFields;
use App\Filament\School\Resources\FeeItems\FeeItemResource;
use App\Models\AcademicTerm;
use App\Models\FeeItem;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

class ManageFeeItems extends ManageRecords
{
    protected static string $resource = FeeItemResource::class;

    public function getSubheading(): ?string
    {
        return 'What each class pays per term. A student\'s bill includes every item for their class and whether they board.';
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->copyFromTermAction(),
            CreateAction::make()
                ->label('Add fee item'),
        ];
    }

    /**
     * Most schools charge the same each term, so copy a term's fee
     * structure instead of typing it again.
     */
    protected function copyFromTermAction(): Action
    {
        return Action::make('copyFromTerm')
            ->label('Copy from another term')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->color('gray')
            ->authorize('create', FeeItem::class)
            ->modalSubmitActionLabel('Copy fees')
            ->schema([
                FeeFields::term('from_term_id')
                    ->label('Copy the fees of')
                    ->default(null),
                FeeFields::term('to_term_id')
                    ->label('Into'),
            ])
            ->action(function (array $data): void {
                $schoolId = Filament::getTenant()->getKey();
                $from = AcademicTerm::query()->withoutGlobalScopes()->where('school_id', $schoolId)->findOrFail($data['from_term_id']);
                $to = AcademicTerm::query()->withoutGlobalScopes()->where('school_id', $schoolId)->findOrFail($data['to_term_id']);

                if ($from->is($to)) {
                    Notification::make()->title('Choose two different terms')->danger()->send();

                    return;
                }

                $existing = $to->feeItems()->withoutGlobalScopes()->pluck('name')->map(fn (string $name): string => mb_strtolower($name))->all();
                $copied = 0;

                DB::transaction(function () use ($from, $to, $existing, &$copied): void {
                    foreach ($from->feeItems()->withoutGlobalScopes()->with('classes')->get() as $item) {
                        if (in_array(mb_strtolower($item->name), $existing, true)) {
                            continue;
                        }

                        $copy = $item->replicate(['academic_term_id']);
                        $copy->academic_term_id = $to->getKey();
                        $copy->save();
                        $copy->classes()->sync($item->classes->modelKeys());
                        $copied++;
                    }
                });

                Notification::make()
                    ->title($copied > 0 ? "Copied {$copied} fee items into {$to->name}" : 'Nothing to copy')
                    ->body($copied > 0 ? 'Change any amounts that are different this term.' : 'Those items are already in that term.')
                    ->success()
                    ->send();
            });
    }
}
