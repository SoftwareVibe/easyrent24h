<?php

namespace App\Filament\Resources\Vehicles\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class VehicleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nome')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                TextInput::make('subheader')
                    ->label('Sottotitolo'),
                Select::make('vehicle_type_id')
                    ->label('Tipo')
                    ->relationship('type', 'name'),
                TextInput::make('stock')
                    ->label('Mezzi disponibili (stock)')
                    ->required()
                    ->numeric()
                    ->default(1),
                Toggle::make('price_on_request')
                    ->label('Prezzo su richiesta'),
                TextInput::make('custom_price_text'),
                TextInput::make('sale_badge')
                    ->label('Badge offerta'),
                Textarea::make('description')
                    ->label('Descrizione')
                    ->columnSpanFull(),
                TextInput::make('video_url')
                    ->url(),
                TextInput::make('image_path')
                    ->label('Immagine (path frontend, es. /img/vehicles/foo.webp)')
                    ->afterStateHydrated(function (TextInput $component, $record) {
                        $component->state($record?->gallery[0] ?? null);
                    })
                    ->dehydrated(false)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, $record) {
                        $record?->update(['gallery' => $state ? [$state] : null]);
                    }),
                Toggle::make('no_same_day')
                    ->label('Non noleggiabile in giornata'),
                TextInput::make('min_days')
                    ->label('Min giorni')
                    ->numeric(),
                TextInput::make('max_days')
                    ->label('Max giorni')
                    ->numeric(),
                TextInput::make('sort_order')
                    ->label('Ordinamento')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('active')
                    ->label('Attivo')
                    ->required(),
                Select::make('pickupLocations')
                    ->label('Località di ritiro')
                    ->relationship('pickupLocations', 'name')
                    ->multiple()
                    ->preload(),
                Select::make('dropoffLocations')
                    ->label('Località di riconsegna')
                    ->relationship('dropoffLocations', 'name')
                    ->multiple()
                    ->preload(),
                Select::make('extras')
                    ->label('Extra disponibili')
                    ->relationship('extras', 'name')
                    ->multiple()
                    ->preload(),
                Select::make('features')
                    ->label('Caratteristiche')
                    ->relationship('features', 'name')
                    ->multiple()
                    ->preload(),
            ]);
    }
}
