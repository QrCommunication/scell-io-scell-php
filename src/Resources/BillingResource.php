<?php

declare(strict_types=1);

namespace Scell\Sdk\Resources;

use Scell\Sdk\DTOs\BillingInvoice;
use Scell\Sdk\DTOs\BillingTransaction;
use Scell\Sdk\DTOs\BillingUsage;
use Scell\Sdk\DTOs\CreditPack;
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

    /**
     * Liste les packs de credits disponibles (vue tenant authentifie).
     *
     * Endpoint backend : `GET /api/v1/tenant/billing/packs`.
     * Equivalent fonctionnel de `$client->creditPacks()->list()` qui appelle
     * `GET /api/v1/packs/public` (lecture sans auth). Cette methode-ci force
     * le contexte tenant — utile si vous voulez tracker les vues / overrides
     * tarifaires specifiques au tenant authentifie.
     *
     * @return CreditPack[]
     *
     * @since 2.24.0
     */
    public function listPacks(): array
    {
        $response = $this->http->get('tenant/billing/packs');
        $items = $response['data'] ?? [];

        return array_map(
            fn(array $data): CreditPack => CreditPack::fromArray($data),
            $items,
        );
    }

    /**
     * Initie l'achat d'un pack de credits.
     *
     * Endpoint backend : `POST /api/v1/tenant/billing/packs/{packSlug}/checkout`.
     *
     * Comportement selon environnement :
     *  - **Sandbox** (cle `sk_test_*`) : credit direct, pas d'appel Stripe ni
     *    Factur-X. Reponse `{success, mode: 'sandbox_bypass', credit_eur, ...}`.
     *  - **Production** (cle `sk_live_*`) : cree un Stripe PaymentIntent.
     *    Reponse `{success, mode: 'live'|'live_existing', client_secret, payment_intent_id}`.
     *    Le webhook `payment_intent.succeeded` declenche ensuite la creation
     *    de la Factur-X + credit balance.
     *
     * Idempotent en mode live : si un PaymentIntent actif existe deja pour ce
     * pack (recent <24h, statut `requires_*` ou `processing`), retourne le meme
     * `client_secret`.
     *
     * @param string $packSlug Slug stable du pack (`starter`, `pro`, `business`, ...)
     *
     * @return array<string, mixed> Raw response (shape varie selon mode)
     *
     * @throws \Scell\Sdk\Exceptions\ScellException 404 si pack inconnu / inactif,
     *         401 si tenant non resolu, 500 si erreur Stripe
     *
     * @since 2.24.0
     */
    public function checkoutPack(string $packSlug): array
    {
        return $this->http->post("tenant/billing/packs/{$packSlug}/checkout");
    }
}
