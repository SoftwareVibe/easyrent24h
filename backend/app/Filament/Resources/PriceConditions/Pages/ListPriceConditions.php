<?php

namespace App\Filament\Resources\PriceConditions\Pages;

use App\Filament\Resources\PriceConditions\PriceConditionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPriceConditions extends ListRecords
{
    protected static string $resource = PriceConditionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
