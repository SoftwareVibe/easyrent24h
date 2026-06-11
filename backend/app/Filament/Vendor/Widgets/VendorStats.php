<?php

namespace App\Filament\Vendor\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Riepilogo commissioni del rappresentante loggato. */
class VendorStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $vendor = auth()->user()?->vendor;
        if (! $vendor) {
            return [];
        }

        $accrued = $vendor->commissionAccrued();
        $paid = $vendor->commissionPaid();

        return [
            Stat::make('Coupon', $vendor->coupon?->code ?? '—')
                ->description('Commissione '.number_format((float) $vendor->commission_percent, 2).'%'),
            Stat::make('Ordini generati', (string) $vendor->orders()->count()),
            Stat::make('Commissioni maturate', '€'.number_format($accrued, 2, ',', '.')),
            Stat::make('Saldo da ricevere', '€'.number_format(max(0, $accrued - $paid), 2, ',', '.'))
                ->description('Già pagato: €'.number_format($paid, 2, ',', '.')),
        ];
    }
}
