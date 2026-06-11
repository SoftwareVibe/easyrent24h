<?php

namespace App\Filament\Resources\PriceConditions;

use App\Filament\Resources\PriceConditions\Pages\CreatePriceCondition;
use App\Filament\Resources\PriceConditions\Pages\EditPriceCondition;
use App\Filament\Resources\PriceConditions\Pages\ListPriceConditions;
use App\Filament\Resources\PriceConditions\Schemas\PriceConditionForm;
use App\Filament\Resources\PriceConditions\Tables\PriceConditionsTable;
use App\Models\PriceCondition;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PriceConditionResource extends Resource
{
    protected static ?string $model = PriceCondition::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return PriceConditionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PriceConditionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPriceConditions::route('/'),
            'create' => CreatePriceCondition::route('/create'),
            'edit' => EditPriceCondition::route('/{record}/edit'),
        ];
    }
}
