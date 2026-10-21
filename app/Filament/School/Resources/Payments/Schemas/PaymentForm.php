<?php

namespace App\Filament\School\Resources\Payments\Schemas;

use App\Filament\School\Fields\FeeFields;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Payment received')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema(FeeFields::paymentFields()),
            ]);
    }
}
