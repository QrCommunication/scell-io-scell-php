<?php

declare(strict_types=1);

namespace Scell\Sdk;

use Scell\Sdk\Http\HttpClient;
use Scell\Sdk\Resources\BillingResource;
use Scell\Sdk\Resources\FiscalResource;
use Scell\Sdk\Resources\InvoiceResource;
use Scell\Sdk\Resources\SignatureResource;
use Scell\Sdk\Resources\StatsResource;
use Scell\Sdk\Resources\SubTenantResource;
use Scell\Sdk\Resources\TenantCreditNoteResource;
use Scell\Sdk\Resources\TenantDirectInvoiceResource;
use Scell\Sdk\Resources\TenantIncomingInvoiceResource;
use Scell\Sdk\Resources\TenantInvoiceResource;

/**
 * API Client for server-to-server integration.
 * Uses X-API-Key header with sk_live_* or sk_test_* keys.
 *
 * Provides access to both legacy invoice/signature endpoints
 * and tenant management endpoints. For dedicated tenant operations,
 * prefer ScellTenantClient which uses X-Tenant-Key header.
 *
 * @example
 * ```php
 * // Initialisation avec API Key
 * $api = ScellApiClient::withApiKey('sk_live_...');
 *
 * // Ou mode sandbox
 * $api = ScellApiClient::sandbox('sk_test_...');
 *
 * // Creer une facture
 * $invoice = $api->invoices()->builder()
 *     ->invoiceNumber('FACT-2024-001')
 *     ->outgoing()
 *     ->facturX()
 *     ->issueDate(new \DateTime())
 *     ->seller('12345678901234', 'Ma Societe', new Address(...))
 *     ->buyer('98765432109876', 'Client SA', new Address(...))
 *     ->addLine('Prestation', 1, 1000.00, 20.0)
 *     ->create();
 *
 * // Gerer les sub-tenants
 * $subTenant = $api->subTenants()->create([...]);
 * $stats = $api->stats()->overview();
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
    private ?TenantDirectInvoiceResource $directInvoices = null;
    private ?TenantIncomingInvoiceResource $incomingInvoices = null;

    /**
     * Cree une instance du client API.
     *
     * @param string $apiKey Cle API (commence par sk_live_ ou sk_test_)
     * @param Config|null $config Configuration optionnelle
     */
    private function __construct(
        string $apiKey,
        ?Config $config = null
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

        $this->http->withApiKey($apiKey);
    }

    /**
     * Cree un client avec une API Key.
     *
     * @param string $apiKey Cle API (sk_live_... ou sk_test_...)
     * @param Config|null $config Configuration optionnelle
     */
    public static function withApiKey(string $apiKey, ?Config $config = null): self
    {
        return new self($apiKey, $config);
    }

    /**
     * Cree un client en mode sandbox.
     *
     * @param string $apiKey Cle API sandbox (sk_test_...)
     */
    public static function sandbox(string $apiKey): self
    {
        return new self($apiKey, Config::sandbox());
    }

    /**
     * Cree un client pour le developpement local.
     *
     * @param string $apiKey Cle API
     * @param string $baseUrl URL de l'API locale
     */
    public static function local(string $apiKey, string $baseUrl = 'http://localhost:8000/api/v1'): self
    {
        return new self($apiKey, Config::local($baseUrl));
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
     * Resource pour la conformite fiscale NF525.
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
