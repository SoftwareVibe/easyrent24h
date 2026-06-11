<?php

namespace App\Filament\Resources\Bookings\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Schema;

class BookingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('order_id')
                    ->relationship('order', 'id'),
                Select::make('vehicle_id')
                    ->relationship('vehicle', 'name')
                    ->required(),
                DatePicker::make('date_start')
                    ->required(),
                DatePicker::make('date_end')
                    ->required(),
                TimePicker::make('time_start'),
                TimePicker::make('time_end'),
                Select::make('pickup_location_id')
                    ->relationship('pickupLocation', 'name'),
                Select::make('dropoff_location_id')
                    ->relationship('dropoffLocation', 'name'),
                TextInput::make('quantity')
                    ->required()
                    ->numeric()
                    ->default(1),
                Select::make('status')
                    ->options([
                        'pending' => 'In attesa',
                        'confirmed' => 'Confermata',
                        'block' => 'Blocco manuale (date non prenotabili)',
                        'cancelled' => 'Annullata',
                    ])
                    ->required()
                    ->default('confirmed'),
                TextInput::make('days')
                    ->required()
                    ->numeric()
                    ->default(1),
                TextInput::make('price')
                    ->numeric()
                    ->prefix('€'),
                TextInput::make('extras_total')
                    ->numeric(),
            ]);
    }
}
