<?php

namespace App\Filament\Vendor\Widgets;

use App\Models\Order;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Ordini generati dal coupon del rappresentante loggato (sola lettura). */
class VendorOrdersTable extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $vendor = auth()->user()?->vendor;

        return $table
            ->heading('I tuoi ordini')
            ->query(
                Order::query()
                    ->where('coupon_code', $vendor?->coupon?->code ?? '__nessuno__')
                    ->whereIn('status', ['deposit_paid', 'paid'])
                    ->latest()
            )
            ->columns([
                TextColumn::make('number')->label('Ordine'),
                TextColumn::make('created_at')->label('Data')->date('d/m/Y'),
                TextColumn::make('total')->label('Totale')->money('EUR'),
                TextColumn::make('status')->label('Stato')->badge(),
            ])
            ->paginated([10, 25]);
    }
}
