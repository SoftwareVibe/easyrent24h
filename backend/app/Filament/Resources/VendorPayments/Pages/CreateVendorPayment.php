<?php

namespace App\Filament\Resources\VendorPayments\Pages;

use App\Filament\Resources\VendorPayments\VendorPaymentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVendorPayment extends CreateRecord
{
    protected static string $resource = VendorPaymentResource::class;
}
