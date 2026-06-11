<?php

namespace App\Filament\Resources\Vehicles\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Listino del veicolo: tariffa base (senza condizione) + tariffe condizionali. */
class PricesRelationManager extends RelationManager
{
    protected static string $relationship = 'prices';

    protected static ?string $title = 'Listino';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('price_condition_id')
                    ->label('Condizione (vuota = tariffa base)')
                    ->relationship('condition', 'name')
                    ->nullable()
                    ->searchable()
                    ->preload(),
                TextInput::make('price')
                    ->label('Prezzo €/giorno')
                    ->numeric()
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('condition.name')
                    ->label('Condizione')
                    ->placeholder('Tariffa base'),
                TextColumn::make('price')
                    ->label('Prezzo')
                    ->money('EUR'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
