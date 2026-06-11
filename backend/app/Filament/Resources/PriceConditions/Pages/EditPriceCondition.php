<?php

namespace App\Filament\Resources\PriceConditions\Pages;

use App\Filament\Resources\PriceConditions\PriceConditionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPriceCondition extends EditRecord
{
    protected static string $resource = PriceConditionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
