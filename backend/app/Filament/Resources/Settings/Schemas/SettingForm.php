<?php

namespace App\Filament\Resources\Settings\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Cache;

class SettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('key')
                    ->label('Chiave')
                    ->disabledOn('edit')
                    ->required(),
                Textarea::make('value')
                    ->label('Valore (JSON)')
                    ->rows(4)
                    ->columnSpanFull()
                    ->afterStateHydrated(function (Textarea $component, $state) {
                        $component->state(json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    })
                    ->dehydrateStateUsing(fn ($state) => json_decode($state, true))
                    ->rule('json')
                    ->afterStateUpdated(fn () => Cache::forget('settings.all')),
            ]);
    }
}
