<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('number')
                    ->required(),
                TextInput::make('status')
                    ->required()
                    ->default('pending'),
                TextInput::make('customer_name'),
                TextInput::make('customer_email')
                    ->email(),
                TextInput::make('customer_phone')
                    ->tel(),
                TextInput::make('locale')
                    ->required()
                    ->default('en'),
                TextInput::make('currency')
                    ->required()
                    ->default('EUR'),
                TextInput::make('subtotal')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('extras_total')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('total')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('deposit_amount')
                    ->numeric(),
                TextInput::make('coupon_code'),
                Textarea::make('notes')
                    ->columnSpanFull(),
                TextInput::make('discount_total')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('paid_total')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
