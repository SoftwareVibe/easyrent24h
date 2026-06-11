<?php

namespace App\Filament\Resources\Locations\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class LocationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                Select::make('hub_id')
                    ->label('Hub logistico')
                    ->relationship('hub', 'name'),
                Toggle::make('activate_shipping')
                    ->label('Richiede indirizzo di consegna'),
                TimePicker::make('window_start')
                    ->label('Finestra oraria: dalle (vuoto = orario globale)')
                    ->seconds(false),
                TimePicker::make('window_end')
                    ->label('Finestra oraria: alle')
                    ->seconds(false),
                Toggle::make('endpoints_only')
                    ->label('Solo apertura/chiusura (es. Amalfi: 08:00 o 20:00)'),
            ]);
    }
}
