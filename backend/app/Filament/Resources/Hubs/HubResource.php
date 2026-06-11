<?php

namespace App\Filament\Resources\Hubs;

use App\Filament\Resources\Hubs\Pages\CreateHub;
use App\Filament\Resources\Hubs\Pages\EditHub;
use App\Filament\Resources\Hubs\Pages\ListHubs;
use App\Filament\Resources\Hubs\Schemas\HubForm;
use App\Filament\Resources\Hubs\Tables\HubsTable;
use App\Models\Hub;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class HubResource extends Resource
{
    protected static ?string $model = Hub::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return HubForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HubsTable::configure($table);
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
            'index' => ListHubs::route('/'),
            'create' => CreateHub::route('/create'),
            'edit' => EditHub::route('/{record}/edit'),
        ];
    }
}
