<?php

namespace App\Filament\Support;

use Filament\Navigation\MenuItem;

/** Voci del menu utente per il cambio lingua (condivise tra i pannelli). */
class LocaleMenu
{
    /** @return MenuItem[] */
    public static function items(): array
    {
        $labels = ['en' => 'English', 'it' => 'Italiano', 'es' => 'Español'];

        return collect($labels)->map(fn (string $label, string $locale) => MenuItem::make("locale-{$locale}")
            ->label(fn () => (app()->getLocale() === $locale ? '✓ ' : '').$label)
            ->icon('heroicon-o-language')
            ->url(fn () => route('locale.switch', ['locale' => $locale]))
        )->values()->all();
    }
}
