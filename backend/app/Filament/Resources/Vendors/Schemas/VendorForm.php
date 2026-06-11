<?php

namespace App\Filament\Resources\Vendors\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class VendorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email(),
                TextInput::make('phone')
                    ->tel(),
                Select::make('coupon_id')
                    ->label('Coupon assegnato')
                    ->relationship('coupon', 'code')
                    ->searchable()
                    ->preload(),
                TextInput::make('commission_percent')
                    ->label('Commissione %')
                    ->required()
                    ->numeric()
                    ->default(0),
                Select::make('user_id')
                    ->relationship('user', 'name'),
                Toggle::make('active')
                    ->required(),
                TextInput::make('legacy_id')
                    ->numeric(),
            ]);
    }
}
