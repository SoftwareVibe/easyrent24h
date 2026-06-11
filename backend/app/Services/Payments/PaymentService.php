<?php

namespace App\Services\Payments;

use App\Mail\OrderConfirmationMail;
use App\Models\Booking;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Orchestrazione pagamenti: acconto/saldo (ex plugin AWCDP), aggiornamento
 * stato ordine e calendario, email di conferma, rimborsi.
 */
class PaymentService
{
    /** Stati ordine: pending -> deposit_paid -> paid; cancelled/refunded liberano le date. */
    public function driver(string $provider): PaymentDriver
    {
        return match ($provider) {
            'stripe' => app(StripeDriver::class),
            'paypal' => app(PayPalDriver::class),
            'offline' => app(OfflineDriver::class),
            default => throw ValidationException::withMessages(['provider' => __('Unsupported payment provider')]),
        };
    }

    public function enabledProviders(): array
    {
        $providers = [];
        if (config('services.stripe.secret')) {
            $providers[] = ['id' => 'stripe', 'publishable_key' => config('services.stripe.key')];
        }
        if (config('services.paypal.client_id')) {
            $providers[] = ['id' => 'paypal'];
        }
        if (config('services.offline_payments', true)) {
            $providers[] = ['id' => 'offline'];
        }

        return $providers;
    }

    /** Avvia un pagamento (acconto, saldo o totale) per un ordine. */
    public function initiate(Order $order, string $provider, string $type): array
    {
        if (in_array($order->status, ['cancelled', 'refunded'], true)) {
            throw ValidationException::withMessages(['order' => __('Order is not payable')]);
        }

        $amount = $this->amountFor($order, $type);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['type' => __('Nothing to pay')]);
        }

        $payment = $order->payments()->create([
            'provider' => $provider,
            'type' => $type,
            'amount' => $amount,
            'currency' => $order->currency,
            'status' => Payment::STATUS_PENDING,
        ]);

        $result = $this->driver($provider)->initiate($payment);

        // l'offline riesce subito: chiudi il giro come farebbe il webhook
        if (($result['status'] ?? null) === 'succeeded') {
            $this->markSucceeded($payment->fresh());
        }

        return ['payment_id' => $payment->id] + $result;
    }

    /** Cattura/conferma (PayPal return, o ri-verifica Stripe senza webhook). */
    public function confirm(Payment $payment, array $params = []): bool
    {
        if ($payment->status === Payment::STATUS_SUCCEEDED) {
            return true;
        }

        if ($this->driver($payment->provider)->confirm($payment, $params)) {
            $this->markSucceeded($payment);

            return true;
        }

        $payment->update(['status' => Payment::STATUS_FAILED]);

        return false;
    }

    /** Registra l'esito positivo e aggiorna ordine + calendario + email. */
    public function markSucceeded(Payment $payment): void
    {
        if ($payment->status !== Payment::STATUS_SUCCEEDED) {
            $payment->update(['status' => Payment::STATUS_SUCCEEDED]);
        }

        $order = $payment->order;
        $paid = (float) $order->payments()->where('status', Payment::STATUS_SUCCEEDED)->sum('amount');
        $status = $paid >= (float) $order->total - 0.01 ? 'paid' : 'deposit_paid';

        $wasPending = $order->status === 'pending';
        $order->update(['paid_total' => $paid, 'status' => $status]);
        $order->bookings()->update(['status' => Booking::STATUS_CONFIRMED]);

        if ($wasPending && $order->customer_email) {
            Mail::to($order->customer_email)
                ->locale($order->locale ?: 'en')
                ->send(new OrderConfirmationMail($order->fresh('bookings')));
        }
    }

    /** Annulla l'ordine: rimborsa i pagamenti riusciti e libera le date. */
    public function cancel(Order $order, bool $refund = true): void
    {
        if ($refund) {
            foreach ($order->payments()->where('status', Payment::STATUS_SUCCEEDED)->get() as $payment) {
                $this->driver($payment->provider)->refund($payment);
            }
        }

        $hadPayments = $order->payments()->where('status', Payment::STATUS_REFUNDED)->exists();
        $order->update(['status' => $hadPayments ? 'refunded' : 'cancelled']);
        $order->bookings()->update(['status' => Booking::STATUS_CANCELLED]);
    }

    private function amountFor(Order $order, string $type): float
    {
        $remaining = (float) $order->total - (float) $order->paid_total;

        return match ($type) {
            'deposit' => $order->paid_total > 0
                ? 0.0
                : (float) ($order->deposit_amount ?: $order->total),
            'balance', 'full' => round($remaining, 2),
            default => 0.0,
        };
    }
}
