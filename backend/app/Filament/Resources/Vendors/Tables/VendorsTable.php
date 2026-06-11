<?php

namespace App\Filament\Resources\Vendors\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VendorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('coupon.code')
                    ->label('Coupon')
                    ->searchable(),
                TextColumn::make('commission_percent')
                    ->label('Commissione %')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->searchable(),
                IconColumn::make('active')
                    ->boolean(),
                TextColumn::make('legacy_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('qr')
                    ->label('QR coupon')
                    ->icon('heroicon-o-qr-code')
                    ->url(fn ($record) => $record->coupon
                        ? route('qr.coupon', ['code' => $record->coupon->code])
                        : null)
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => $record->coupon !== null),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
