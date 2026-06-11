<?php

namespace App\Services\Payments;

use App\Models\Payment;

/**
 * Driver di test/sviluppo: il pagamento riesce subito, senza gateway.
 * Usato anche per ordini "paga al ritiro" se abilitato.
 */
class OfflineDriver implements PaymentDriver
{
    public function initiate(Payment $payment): array
    {
        $payment->update(['status' => Payment::STATUS_SUCCEEDED, 'provider_ref' => 'offline-'.$payment->id]);

        return ['status' => 'succeeded'];
    }

    public function confirm(Payment $payment, array $params = []): bool
    {
        return $payment->status === Payment::STATUS_SUCCEEDED;
    }

    public function refund(Payment $payment): bool
    {
        $payment->update(['status' => Payment::STATUS_REFUNDED]);

        return true;
    }
}
