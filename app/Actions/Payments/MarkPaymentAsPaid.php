<?php

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Records that the customer paid. Payment state is independent from the
 * order: this never touches orders.status nor its status history.
 */
class MarkPaymentAsPaid
{
    public function execute(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if (! $payment->status->canTransitionTo(PaymentStatus::Paid)) {
                throw new \RuntimeException(
                    "Este pagamento não pode ser marcado como pago (status: {$payment->status->label()})."
                );
            }

            $payment->update([
                'status'  => PaymentStatus::Paid,
                'paid_at' => now(),
            ]);

            return $payment;
        });
    }
}
