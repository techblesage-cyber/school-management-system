<?php

namespace App\Filament\School\Resources\Invoices\Schemas;

use App\Filament\School\Fields\FeeFields;
use App\Models\Invoice;
use App\Support\Money;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class InvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Invoice')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        FeeFields::student()
                            ->disabledOn('edit')
                            ->rule(fn (Get $get, string $operation): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $operation): void {
                                $exists = $operation === 'create' && Invoice::query()->withoutGlobalScopes()
                                    ->where('student_id', $value)
                                    ->where('academic_term_id', $get('academic_term_id'))
                                    ->exists();

                                if ($exists) {
                                    $fail('This student already has an invoice for that term. Open it and add a line instead.');
                                }
                            }),
                        FeeFields::term()
                            ->disabledOn('edit'),
                        DatePicker::make('issued_on')
                            ->label('Date')
                            ->default(now())
                            ->required(),
                    ]),
                Section::make('Lines')
                    ->description('Leave empty when creating to fill it from the fee structure. Use a minus amount for a discount or bursary.')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('items')
                            ->hiddenLabel()
                            ->relationship()
                            ->schema([
                                TextInput::make('description')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(2),
                                FeeFields::amount()
                                    ->helperText(null),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->addActionLabel('Add line')
                            ->live(),
                        Text::make(fn (Get $get): string => 'Total: '.Money::format(collect($get('items'))->sum(fn (array $item): int => (int) ($item['amount'] ?? 0))))
                            ->weight('bold'),
                    ]),
                Textarea::make('notes')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }
}
