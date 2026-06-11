<?php

namespace App\Filament\Resources\Extras\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ExtraForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('price')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->prefix('$'),
                TextInput::make('type')
                    ->required()
                    ->default('total'),
                TextInput::make('max_qty')
                    ->required()
                    ->numeric()
                    ->default(1),
                Toggle::make('always_included')
                    ->required(),
                Textarea::make('translations')
                    ->columnSpanFull(),
                TextInput::make('legacy_term_id')
                    ->numeric(),
            ]);
    }
}
