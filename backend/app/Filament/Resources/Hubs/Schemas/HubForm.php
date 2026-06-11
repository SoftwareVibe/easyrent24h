<?php

namespace App\Filament\Resources\Hubs\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class HubForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
            ]);
    }
}
