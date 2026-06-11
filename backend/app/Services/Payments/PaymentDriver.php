<?php

namespace App\Services\Payments;

use App\Models\Payment;

interface PaymentDriver
{
    /**
     * Avvia il pagamento e ritorna i dati che servono al frontend
     * (client_secret per Stripe, approve link per PayPal, esito per offline).
     */
    public function initiate(Payment $payment): array;

    /** Conferma/cattura il pagamento (capture PayPal, verifica offline). */
    public function confirm(Payment $payment, array $params = []): bool;

    /** Rimborsa un pagamento riuscito. */
    public function refund(Payment $payment): bool;
}
