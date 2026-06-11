<?php

namespace App\Filament\Resources\PriceConditions\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PriceConditionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('days_from')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('days_to')
                    ->numeric(),
                TextInput::make('days_first')
                    ->numeric(),
                Toggle::make('fixed_price')
                    ->required(),
                Textarea::make('weekdays')
                    ->columnSpanFull(),
                Textarea::make('month_days')
                    ->columnSpanFull(),
                Textarea::make('months')
                    ->columnSpanFull(),
                Textarea::make('years')
                    ->columnSpanFull(),
                DatePicker::make('date_from'),
                DatePicker::make('date_to'),
                Textarea::make('pickup_location_ids')
                    ->columnSpanFull(),
                Textarea::make('dropoff_location_ids')
                    ->columnSpanFull(),
                Textarea::make('vehicle_type_ids')
                    ->columnSpanFull(),
                Toggle::make('active')
                    ->required(),
                TextInput::make('legacy_term_id')
                    ->numeric(),
            ]);
    }
}
