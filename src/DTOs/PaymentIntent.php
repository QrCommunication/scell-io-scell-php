<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

/**
 * PaymentIntent — returned by BillingResource::payInvoice().
 *
 * Contains the Stripe client_secret needed to confirm payment client-side
 * via Stripe.js / confirmCardPayment().
 *
 * @since 2.2.0
 */
readonly class PaymentIntent
{
    public function __construct(
        /** Stripe PaymentIntent client_secret — pass to Stripe.js confirmCardPayment() */
        public string $clientSecret,
        /** Stripe PaymentIntent ID (pi_...) */
        public string $paymentIntentId,
        /** Amount in smallest currency unit (e.g. cents for EUR) */
        public int $amount,
        /** ISO 4217 currency code (e.g. "eur") */
        public string $currency,
        /** Stripe PaymentIntent status (e.g. "requires_payment_method", "succeeded") */
        public string $status,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            clientSecret: $data['client_secret'],
            paymentIntentId: $data['payment_intent_id'],
            amount: (int) $data['amount'],
            currency: $data['currency'],
            status: $data['status'],
        );
    }
}
