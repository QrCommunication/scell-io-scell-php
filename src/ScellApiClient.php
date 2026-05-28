<?php

declare(strict_types=1);

namespace Scell\Sdk;

use Scell\Sdk\Http\HttpClient;
use Scell\Sdk\Resources\BillingResource;
use Scell\Sdk\Resources\BuyerResource;
use Scell\Sdk\Resources\CreditPacksResource;
use Scell\Sdk\Resources\FiscalResource;
use Scell\Sdk\Resources\InvoiceResource;
use Scell\Sdk\Resources\OnboardingResource;
use Scell\Sdk\Resources\SignatureResource;
use Scell\Sdk\Resources\StatsResource;
use Scell\Sdk\Resources\SubTenantResource;
use Scell\Sdk\Resources\SupplierResource;
use Scell\Sdk\Resources\BrandingResource;
use Scell\Sdk\Resources\TenantCreditNoteResource;
use Scell\Sdk\Resources\TenantDirectInvoiceResource;
use Scell\Sdk\Resources\TenantIncomingInvoiceResource;
use Scell\Sdk\Resources\TenantInvoiceResource;
use Scell\Sdk\Resources\QuoteResource;
use Scell\Sdk\Resources\TenantSignatureResource;

/**
 * API Client for server-to-server integration.
 *
 * Modes d'authentification supportes :
 * - **Secret API Key** (`sk_live_*` / `sk_test_*`) via `withApiKey()` — header `X-API-Key`,
 *   server-side uniquement, donne acces a tous les endpoints.
 * - **Publishable Key** (`pk_live_* / pk_test_*`) via `withPublishableKey()` — header
 *   `X-Publishable-Key`, safe browser/client, restreint aux endpoints widget publics
 *   (`/widget/onboarding/*`). Pour des operations widget c'est ce qu'il FAUT utiliser
 *   (a la place de `ScellPublicClient` quand on veut un seul client).
 *
 * Pour les operations tenant dediees (X-Tenant-Key), preferer `ScellTenantClient`.
 *
 * @example
 * ```php
 * // Server-side (Secret API Key)
 * $api = ScellApiClient::withApiKey('sk_live_...');
 *
 * // Sandbox server-side
 * $api = ScellApiClient::sandbox('sk_test_...');
 *
 * // Widget public (Publishable Key) — header X-Publishable-Key
 * $public = ScellApiClient::withPublishableKey('pk_live_...');
 * $lookup = $public->onboarding()->lookupSirene('12345678901234');
 *
 * // Creer une facture (sk_*)
 * $invoice = $api->invoices()->builder()
 *     ->outgoing()
 *     ->facturX()
 *     ->issueDate(new \DateTime())
 *     ->seller('12345678901234', 'Ma Societe', new Address(...))
 *     ->buyer('98765432109876', 'Client SA', new Address(...))
 *     ->addLine('Prestation', 1, 1000.00, 20.0)
 *     ->create();
 * ```
 */
class ScellApiClient
{
    private readonly HttpClient $http;
    private readonly Config $config;
    private ?InvoiceResource $invoices = null;
    private ?SignatureResource $signatures = null;
    private ?SubTenantResource $subTenants = null;
    private ?FiscalResource $fiscal = null;
    private ?StatsResource $stats = null;
    private ?BillingResource $billing = null;
    private ?TenantCreditNoteResource $creditNotes = null;
    private ?TenantInvoiceResource $tenantInvoices = null;
    private ?TenantSignatureResource $tenantSignatures = null;
    private ?TenantDirectInvoiceResource $directInvoices = null;
    private ?TenantIncomingInvoiceResource $incomingInvoices = null;
    private ?OnboardingResource $onboarding = null;
    private ?BuyerResource $buyers = null;
    private ?SupplierResource $suppliers = null;
    private ?QuoteResource $quotes = null;
    private ?BrandingResource $branding = null;
    private ?CreditPacksResource $creditPacks = null;

    /**
     * Cree une instance du client API.
     *
     * @param string $key Cle API (sk_live_* / sk_test_*) ou publishable (pk_live_* / pk_test_*)
     * @param Config|null $config Configuration optionnelle
     * @param 'api-key'|'publishable-key' $authMode Mode d'auth (selectionne le header HTTP)
     */
    private function __construct(
        string $key,
        ?Config $config = null,
        string $authMode = 'api-key',
    ) {
        $this->config = $config ?? new Config();

        $this->http = new HttpClient(
            baseUrl: $this->config->baseUrl,
            timeout: $this->config->timeout,
            connectTimeout: $this->config->connectTimeout,
            retryAttempts: $this->config->retryAttempts,
            retryDelay: $this->config->retryDelay,
            verifySsl: $this->config->verifySsl,
        );

        match ($authMode) {
            'publishable-key' => $this->http->withPublishableKey($key),
            default => $this->http->withApiKey($key),
        };
    }

    /**
     * Cree un client avec une Secret API Key (server-side).
     *
     * Header HTTP : `X-API-Key`. Donne acces a tous les endpoints API.
     * NE JAMAIS exposer une `sk_*` cote browser/client.
     *
     * @param string $apiKey Cle API (sk_live_... ou sk_test_...)
     * @param Config|null $config Configuration optionnelle
     */
    public static function withApiKey(string $apiKey, ?Config $config = null): self
    {
        return new self($apiKey, $config, 'api-key');
    }

    /**
     * Cree un client avec une Publishable Key (widget public).
     *
     * Header HTTP : `X-Publishable-Key`. Restreint aux endpoints widget publics
     * (`/widget/onboarding/sirene/lookup`, `/widget/onboarding/sub-tenant`, ...).
     * Safe a exposer cote browser/client/widget partenaire.
     *
     * @param string $publishableKey Cle publishable (pk_live_... ou pk_test_...)
     * @param Config|null $config Configuration optionnelle
     *
     * @example
     * ```php
     * $public = ScellApiClient::withPublishableKey('pk_live_xxx');
     * $lookup = $public->onboarding()->lookupSirene('12345678901234');
     * ```
     */
    public static function withPublishableKey(string $publishableKey, ?Config $config = null): self
    {
        return new self($publishableKey, $config, 'publishable-key');
    }

    /**
     * Cree un client en mode sandbox.
     *
     * @param string $apiKey Cle API sandbox (sk_test_... ou pk_test_...)
     */
    public static function sandbox(string $apiKey): self
    {
        $authMode = str_starts_with($apiKey, 'pk_') ? 'publishable-key' : 'api-key';
        return new self($apiKey, Config::sandbox(), $authMode);
    }

    /**
     * Cree un client pour le developpement local.
     *
     * @param string $apiKey Cle API (sk_* ou pk_*)
     * @param string $baseUrl URL de l'API locale
     */
    public static function local(string $apiKey, string $baseUrl = 'http://localhost:8000/api/v1'): self
    {
        $authMode = str_starts_with($apiKey, 'pk_') ? 'publishable-key' : 'api-key';
        return new self($apiKey, Config::local($baseUrl), $authMode);
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
     * Resource pour les sub-tenants (praticiens).
     */
    public function subTenants(): SubTenantResource
    {
        return $this->subTenants ??= new SubTenantResource($this->http);
    }

    /**
     * Resource pour la conformite fiscale ISCA.
     */
    public function fiscal(): FiscalResource
    {
        return $this->fiscal ??= new FiscalResource($this->http);
    }

    /**
     * Resource pour les statistiques.
     */
    public function stats(): StatsResource
    {
        return $this->stats ??= new StatsResource($this->http);
    }

    /**
     * Resource pour la facturation plateforme.
     */
    public function billing(): BillingResource
    {
        return $this->billing ??= new BillingResource($this->http);
    }

    /**
     * Resource pour les avoirs (credit notes).
     */
    public function creditNotes(): TenantCreditNoteResource
    {
        return $this->creditNotes ??= new TenantCreditNoteResource($this->http);
    }

    /**
     * Resource pour les factures des sub-tenants (create, submit, update, delete).
     */
    public function tenantInvoices(): TenantInvoiceResource
    {
        return $this->tenantInvoices ??= new TenantInvoiceResource($this->http);
    }

    /**
     * Resource pour les signatures scope tenant (URL-nested, depuis SDK v2.7.0).
     *
     * Cible les nouveaux endpoints `GET /tenant/signatures` et
     * `GET /tenant/sub-tenants/{id}/signatures` (auth `X-API-Key sk_*` sans
     * contrainte `company_id`). Pour les operations write (create, remind,
     * cancel, download, audit-trail), continuer d'utiliser `signatures()`.
     */
    public function tenantSignatures(): TenantSignatureResource
    {
        return $this->tenantSignatures ??= new TenantSignatureResource($this->http);
    }

    /**
     * Resource pour les factures directes des sub-tenants.
     */
    public function directInvoices(): TenantDirectInvoiceResource
    {
        return $this->directInvoices ??= new TenantDirectInvoiceResource($this->http);
    }

    /**
     * Resource pour les factures entrantes des sub-tenants.
     */
    public function incomingInvoices(): TenantIncomingInvoiceResource
    {
        return $this->incomingInvoices ??= new TenantIncomingInvoiceResource($this->http);
    }

    /**
     * Resource pour l'onboarding via SuperPDP OAuth2.
     */
    public function onboarding(): OnboardingResource
    {
        return $this->onboarding ??= new OnboardingResource($this->http);
    }

    /**
     * Resource pour le registre des acheteurs (scope tenant + sub_tenant).
     * Reutilisez les buyers via `buyer_id` lors de la creation d'invoices.
     */
    public function buyers(): BuyerResource
    {
        return $this->buyers ??= new BuyerResource($this->http);
    }

    /**
     * Resource pour le registre des fournisseurs (scope tenant + sub_tenant).
     *
     * Miroir cote emetteur du registre des acheteurs : memorise l'identite
     * et l'adresse de facturation de vos partenaires fournisseurs.
     *
     * @since 2.26.0
     */
    public function suppliers(): SupplierResource
    {
        return $this->suppliers ??= new SupplierResource($this->http);
    }

    /**
     * Resource pour les devis (CRUD + send/cancel/convert/duplicate/audit).
     */
    public function quotes(): QuoteResource
    {
        return $this->quotes ??= new QuoteResource($this->http);
    }

    /**
     * Resource pour la configuration de marque (branding) tenant et sub-tenant.
     *
     * Gere le logo, la couleur primaire et les textes des emails/PDFs emis.
     */
    public function branding(): BrandingResource
    {
        return $this->branding ??= new BrandingResource($this->http);
    }

    /**
     * Resource pour les packs de credits Scell.io (one-shot prepaid avec bonus).
     *
     * Lecture publique via `GET /api/v1/packs/public`. Pour acheter un pack,
     * utiliser `$client->billing()->checkoutPack($slug)`.
     *
     * @since 2.24.0
     */
    public function creditPacks(): CreditPacksResource
    {
        return $this->creditPacks ??= new CreditPacksResource($this->http);
    }

    /**
     * Retourne la configuration.
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * Retourne le client HTTP sous-jacent.
     */
    public function getHttpClient(): HttpClient
    {
        return $this->http;
    }
}
