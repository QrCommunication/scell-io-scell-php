<?php

declare(strict_types=1);

namespace Scell\Sdk;

use Scell\Sdk\Http\HttpClient;
use Scell\Sdk\Resources\BalanceResource;
use Scell\Sdk\Resources\BrandingResource;
use Scell\Sdk\Resources\BuyerResource;
use Scell\Sdk\Resources\CompanyResource;
use Scell\Sdk\Resources\CreditPacksResource;
use Scell\Sdk\Resources\InvoiceResource;
use Scell\Sdk\Resources\SignatureResource;
use Scell\Sdk\Resources\SupplierResource;
use Scell\Sdk\Resources\TenantCreditNoteResource;
use Scell\Sdk\Resources\WebhookResource;
use Scell\Sdk\Resources\ApiKeyResource;
use Scell\Sdk\Resources\CreditNoteResource;
use Scell\Sdk\Resources\QuoteResource;

/**
 * Client principal du SDK Scell.io (authentification Bearer token).
 *
 * Utilise ce client pour les operations via le dashboard (utilisateur connecte).
 *
 * @example
 * ```php
 * // Initialisation avec Bearer token
 * $client = new ScellClient($bearerToken);
 *
 * // Ou avec configuration personnalisee
 * $client = new ScellClient($bearerToken, new Config(
 *     baseUrl: 'https://api.scell.io/api/v1',
 *     timeout: 60,
 * ));
 *
 * // Lister les factures
 * $invoices = $client->invoices()->list();
 *
 * // Creer une signature
 * $signature = $client->signatures()->builder()
 *     ->title('Contrat de prestation')
 *     ->documentFromFile('/path/to/contract.pdf')
 *     ->addEmailSigner('Jean', 'Dupont', 'jean@example.com')
 *     ->create();
 * ```
 */
class ScellClient
{
    private readonly HttpClient $http;
    private ?InvoiceResource $invoices = null;
    private ?SignatureResource $signatures = null;
    private ?CompanyResource $companies = null;
    private ?BuyerResource $buyers = null;
    private ?SupplierResource $suppliers = null;
    private ?BalanceResource $balance = null;
    private ?WebhookResource $webhooks = null;
    private ?TenantCreditNoteResource $tenantCreditNotes = null;
    private ?ApiKeyResource $apiKeys = null;
    private ?CreditNoteResource $creditNotes = null;
    private ?QuoteResource $quotes = null;
    private ?BrandingResource $branding = null;
    private ?CreditPacksResource $creditPacks = null;

    /**
     * Cree une instance du client avec Bearer token.
     *
     * @param string $bearerToken Token d'authentification (obtenu via /auth/login)
     * @param Config|null $config Configuration optionnelle
     */
    public function __construct(
        string $bearerToken,
        ?Config $config = null
    ) {
        $config ??= new Config();

        $this->http = new HttpClient(
            baseUrl: $config->baseUrl,
            timeout: $config->timeout,
            connectTimeout: $config->connectTimeout,
            retryAttempts: $config->retryAttempts,
            retryDelay: $config->retryDelay,
            verifySsl: $config->verifySsl,
        );

        $this->http->withBearerToken($bearerToken);
    }

    /**
     * Resource pour les factures.
     */
    public function invoices(): InvoiceResource
    {
        return $this->invoices ??= new InvoiceResource($this->http);
    }

    /**
     * Resource pour les signatures.
     */
    public function signatures(): SignatureResource
    {
        return $this->signatures ??= new SignatureResource($this->http);
    }

    /**
     * Resource pour les entreprises.
     */
    public function companies(): CompanyResource
    {
        return $this->companies ??= new CompanyResource($this->http);
    }

    /**
     * Resource pour le registre des acheteurs (buyers).
     */
    public function buyers(): BuyerResource
    {
        return $this->buyers ??= new BuyerResource($this->http);
    }

    /**
     * Resource pour le registre des fournisseurs (suppliers).
     *
     * Miroir cote emetteur du registre des acheteurs : memorise l'identite
     * et l'adresse de facturation de vos partenaires (scope tenant + sub_tenant).
     *
     * @since 2.26.0
     */
    public function suppliers(): SupplierResource
    {
        return $this->suppliers ??= new SupplierResource($this->http);
    }

    /**
     * Resource pour le solde (LEGACY).
     *
     * @deprecated since 2.24.0. Les endpoints `/api/v1/balance/*` ont ete
     * supprimes cote backend Scell.io le 2026-05-10. Utiliser
     * `$client->billing()` (BillingResource) qui expose `usage()`, `topUp()`,
     * `transactions()`, `invoices()`. Voir la doc de `BalanceResource` pour
     * la table de mapping migration.
     *
     * @see BillingResource Remplacement officiel
     */
    public function balance(): BalanceResource
    {
        return $this->balance ??= new BalanceResource($this->http);
    }

    /**
     * Resource pour les webhooks.
     */
    public function webhooks(): WebhookResource
    {
        return $this->webhooks ??= new WebhookResource($this->http);
    }

    /**
     * Resource pour les avoirs des sub-tenants.
     */
    public function tenantCreditNotes(): TenantCreditNoteResource
    {
        return $this->tenantCreditNotes ??= new TenantCreditNoteResource($this->http);
    }

    /**
     * Resource pour les avoirs (dashboard).
     */
    public function creditNotes(): CreditNoteResource
    {
        return $this->creditNotes ??= new CreditNoteResource($this->http);
    }

    /**
     * Resource pour les cles API.
     */
    public function apiKeys(): ApiKeyResource
    {
        return $this->apiKeys ??= new ApiKeyResource($this->http);
    }

    /**
     * Resource pour les devis.
     */
    public function quotes(): QuoteResource
    {
        return $this->quotes ??= new QuoteResource($this->http);
    }

    /**
     * Resource pour la configuration de marque (branding) tenant et sub-tenant.
     */
    public function branding(): BrandingResource
    {
        return $this->branding ??= new BrandingResource($this->http);
    }

    /**
     * Resource pour les packs de credits Scell.io (lecture publique).
     *
     * Endpoint backend `GET /api/v1/packs/public` ne necessite pas d'auth ;
     * l'expose sur le dashboard permet d'afficher les paliers a vendre dans
     * l'UI billing tenant.
     *
     * @since 2.24.0
     */
    public function creditPacks(): CreditPacksResource
    {
        return $this->creditPacks ??= new CreditPacksResource($this->http);
    }

    /**
     * Retourne le client HTTP sous-jacent.
     *
     * Utile pour les requetes personnalisees.
     */
    public function getHttpClient(): HttpClient
    {
        return $this->http;
    }
}
