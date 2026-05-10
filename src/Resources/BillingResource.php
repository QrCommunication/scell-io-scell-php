<?php

declare(strict_types=1);

namespace Scell\Sdk\Resources;

use Scell\Sdk\DTOs\BillingInvoice;
use Scell\Sdk\DTOs\BillingTransaction;
use Scell\Sdk\DTOs\BillingUsage;
use Scell\Sdk\DTOs\PaginatedResult;
use Scell\Sdk\DTOs\PaymentIntent;
use Scell\Sdk\Http\HttpClient;

class BillingResource
{
    public function __construct(
        private readonly HttpClient $http
    ) {}

    /**
     * @return PaginatedResult<BillingInvoice>
     */
    public function invoices(array $params = []): PaginatedResult
    {
        $response = $this->http->get('tenant/billing/invoices', $params);
        return PaginatedResult::fromArray($response, fn(array $data) => BillingInvoice::fromArray($data));
    }

    public function showInvoice(string $invoiceId): BillingInvoice
    {
        $response = $this->http->get("tenant/billing/invoices/{$invoiceId}");
        return BillingInvoice::fromArray($response['data']);
    }

    public function downloadInvoice(string $invoiceId): string
    {
        return $this->http->getRaw("tenant/billing/invoices/{$invoiceId}/download");
    }

    public function usage(array $params = []): BillingUsage
    {
        $response = $this->http->get('tenant/billing/usage', $params);
        return BillingUsage::fromArray($response['data']);
    }

    public function topUp(array $data): array
    {
        return $this->http->post('tenant/billing/top-up', $data);
    }

    public function confirmTopUp(array $data): array
    {
        return $this->http->post('tenant/billing/top-up/confirm', $data);
    }

    /**
     * @return PaginatedResult<BillingTransaction>
     */
    public function transactions(array $params = []): PaginatedResult
    {
        $response = $this->http->get('tenant/billing/transactions', $params);
        return PaginatedResult::fromArray($response, fn(array $data) => BillingTransaction::fromArray($data));
    }

    /**
     * Initie le paiement Stripe d'une facture plateforme.
     *
     * Retourne un PaymentIntent contenant le `client_secret` a passer
     * a Stripe.js `confirmCardPayment()` pour confirmer le paiement cote client.
     *
     * @param string $invoiceId UUID de la BillingInvoice
     * @return PaymentIntent
     * @throws \Scell\Sdk\Exceptions\ScellException si la facture n'existe pas (404)
     *         ou si son statut ne permet pas le paiement, e.g. draft/paid/cancelled (422)
     *
     * @since 2.2.0
     */
    public function payInvoice(string $invoiceId): PaymentIntent
    {
        $response = $this->http->post("tenant/billing/invoices/{$invoiceId}/pay");
        return PaymentIntent::fromArray($response['data']);
    }
}
