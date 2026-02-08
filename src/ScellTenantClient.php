<?php

declare(strict_types=1);

namespace Scell\Sdk;

use Scell\Sdk\Http\HttpClient;
use Scell\Sdk\Resources\SubTenantResource;
use Scell\Sdk\Resources\TenantDirectCreditNoteResource;
use Scell\Sdk\Resources\TenantDirectInvoiceResource;
use Scell\Sdk\Resources\TenantIncomingInvoiceResource;
use Scell\Sdk\Resources\TenantInvoiceResource;
use Scell\Sdk\Resources\TenantCreditNoteResource;
use Scell\Sdk\Resources\FiscalResource;
use Scell\Sdk\Resources\BillingResource;
use Scell\Sdk\Resources\StatsResource;

/**
 * Client multi-tenant du SDK Scell.io (authentification X-Tenant-Key).
 *
 * Utilise ce client pour les integrations partenaires multi-tenant.
 * Les operations passent par les routes /api/v1/tenant avec X-Tenant-Key header.
 *
 * @example
 * ```php
 * // Initialisation avec Tenant Key
 * $tenant = ScellTenantClient::create('tk_live_...');
 *
 * // Ou mode sandbox
 * $tenant = ScellTenantClient::sandbox('tk_test_...');
 *
 * // Creer une facture directe (sans sub-tenant)
 * $invoice = $tenant->directInvoices()->create([
 *     'direction' => 'outgoing',
 *     'output_format' => 'facturx',
 *     'issue_date' => '2026-01-26',
 *     'seller' => ['siret' => '12345678901234', 'name' => 'Ma Societe', ...],
 *     'buyer' => ['siret' => '98765432109876', 'name' => 'Client SA', ...],
 *     'lines' => [...],
 * ]);
 *
 * // Lister les factures entrantes d'un sub-tenant
 * $incoming = $tenant->incomingInvoices()->listForSubTenant('sub-tenant-uuid');
 *
 * // Accepter une facture entrante
 * $tenant->incomingInvoices()->accept('invoice-uuid');
 *
 * // Gerer les sub-tenants
 * $subTenants = $tenant->subTenants()->list();
 * $subTenant = $tenant->subTenants()->create([
 *     'external_id' => 'CLIENT-001',
 *     'name' => 'Mon Client',
 *     'siret' => '12345678901234',
 * ]);
 * ```
 */
class ScellTenantClient
{
    private readonly HttpClient $http;
    private readonly Config $config;

    private ?SubTenantResource $subTenants = null;
    private ?TenantDirectInvoiceResource $directInvoices = null;
    private ?TenantDirectCreditNoteResource $directCreditNotes = null;
    private ?TenantIncomingInvoiceResource $incomingInvoices = null;
    private ?TenantInvoiceResource $invoices = null;
    private ?TenantCreditNoteResource $creditNotes = null;
    private ?FiscalResource $fiscal = null;
    private ?BillingResource $billing = null;
    private ?StatsResource $detailedStats = null;

    /**
     * Cree une instance du client multi-tenant.
     *
     * @param string $tenantKey Cle tenant (commence par tk_live_ ou tk_test_)
     * @param Config|null $config Configuration optionnelle
     */
    private function __construct(
        string $tenantKey,
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

        $this->http->withTenantKey($tenantKey);
    }

    /**
     * Cree un client multi-tenant.
     *
     * @param string $tenantKey Cle tenant (tk_live_... ou tk_test_...)
     * @param Config|null $config Configuration optionnelle
     *
     * @example
     * ```php
     * $tenant = ScellTenantClient::create('tk_live_abc123...');
     * ```
     */
    public static function create(string $tenantKey, ?Config $config = null): self
    {
        return new self($tenantKey, $config);
    }

    /**
     * Cree un client en mode sandbox.
     *
     * @param string $tenantKey Cle tenant sandbox (tk_test_...)
     *
     * @example
     * ```php
     * $tenant = ScellTenantClient::sandbox('tk_test_abc123...');
     * ```
     */
    public static function sandbox(string $tenantKey): self
    {
        return new self($tenantKey, Config::sandbox());
    }

    /**
     * Cree un client pour le developpement local.
     *
     * @param string $tenantKey Cle tenant
     * @param string $baseUrl URL de l'API locale
     *
     * @example
     * ```php
     * $tenant = ScellTenantClient::local('tk_test_abc123...', 'http://localhost:8000/api/v1');
     * ```
     */
    public static function local(string $tenantKey, string $baseUrl = 'http://localhost:8000/api/v1'): self
    {
        return new self($tenantKey, Config::local($baseUrl));
    }

    /**
     * Resource pour les sub-tenants (clients finaux).
     *
     * Endpoints disponibles:
     * - GET /tenant/sub-tenants - Lister les sub-tenants
     * - POST /tenant/sub-tenants - Creer un sub-tenant
     * - GET /tenant/sub-tenants/{id} - Recuperer un sub-tenant
     * - PUT /tenant/sub-tenants/{id} - Modifier un sub-tenant
     * - DELETE /tenant/sub-tenants/{id} - Supprimer un sub-tenant
     * - GET /tenant/sub-tenants/by-external-id/{externalId} - Rechercher par ID externe
     *
     * @example
     * ```php
     * // Lister les sub-tenants
     * $subTenants = $tenant->subTenants()->list(['per_page' => 50]);
     *
     * // Creer un nouveau sub-tenant
     * $subTenant = $tenant->subTenants()->create([
     *     'external_id' => 'CLIENT-001',
     *     'name' => 'Mon Client SARL',
     *     'siret' => '12345678901234',
     *     'email' => 'contact@client.fr',
     * ]);
     * ```
     */
    public function subTenants(): SubTenantResource
    {
        return $this->subTenants ??= new SubTenantResource($this->http);
    }

    /**
     * Resource pour les factures directes du tenant (sans sub-tenant).
     *
     * Endpoints disponibles:
     * - POST /tenant/invoices - Creer une facture directe
     * - GET /tenant/invoices - Lister toutes les factures
     *
     * @example
     * ```php
     * // Creer une facture directe
     * $invoice = $tenant->directInvoices()->create([
     *     'direction' => 'outgoing',
     *     'output_format' => 'facturx',
     *     'issue_date' => '2026-01-26',
     *     'seller' => [...],
     *     'buyer' => [...],
     *     'lines' => [...],
     * ]);
     *
     * // Lister avec filtres
     * $invoices = $tenant->directInvoices()->list([
     *     'status' => 'validated,transmitted',
     *     'date_from' => '2026-01-01',
     * ]);
     * ```
     */
    public function directInvoices(): TenantDirectInvoiceResource
    {
        return $this->directInvoices ??= new TenantDirectInvoiceResource($this->http);
    }

    /**
     * Resource pour les avoirs directs du tenant (sans sub-tenant).
     *
     * Endpoints disponibles:
     * - POST /tenant/credit-notes - Creer un avoir direct
     * - GET /tenant/credit-notes - Lister tous les avoirs
     *
     * @example
     * ```php
     * // Creer un avoir direct
     * $creditNote = $tenant->directCreditNotes()->create([
     *     'invoice_id' => 'uuid-facture-origine',
     *     'reason' => 'Remboursement partiel',
     *     'type' => 'partial',
     *     'items' => [...],
     * ]);
     *
     * // Lister les avoirs
     * $creditNotes = $tenant->directCreditNotes()->list(['status' => 'draft']);
     * ```
     */
    public function directCreditNotes(): TenantDirectCreditNoteResource
    {
        return $this->directCreditNotes ??= new TenantDirectCreditNoteResource($this->http);
    }

    /**
     * Resource pour les factures entrantes (fournisseurs).
     *
     * Endpoints disponibles:
     * - POST /tenant/sub-tenants/{id}/invoices/incoming - Creer une facture entrante
     * - GET /tenant/sub-tenants/{id}/invoices/incoming - Lister les factures entrantes
     * - GET /tenant/invoices/incoming/{id} - Recuperer une facture entrante
     * - POST /tenant/invoices/incoming/{id}/accept - Accepter
     * - POST /tenant/invoices/incoming/{id}/reject - Rejeter
     * - POST /tenant/invoices/incoming/{id}/mark-paid - Marquer comme payee
     *
     * @example
     * ```php
     * // Creer une facture entrante pour un sub-tenant
     * $invoice = $tenant->incomingInvoices()->create('sub-tenant-uuid', [
     *     'invoice_number' => 'FOURN-2026-001',
     *     'issue_date' => '2026-01-26',
     *     'seller' => [...],  // Le fournisseur
     *     'buyer' => [...],   // Le sub-tenant
     *     'lines' => [...],
     * ]);
     *
     * // Lister les factures entrantes d'un sub-tenant
     * $incoming = $tenant->incomingInvoices()->listForSubTenant('sub-tenant-uuid');
     *
     * // Accepter une facture
     * $tenant->incomingInvoices()->accept('invoice-uuid');
     *
     * // Rejeter avec motif
     * $tenant->incomingInvoices()->reject('invoice-uuid', 'Montant incorrect', 'AMOUNT_ERROR');
     *
     * // Marquer comme payee
     * $tenant->incomingInvoices()->markPaid('invoice-uuid', 'VIR-2026-001');
     * ```
     */
    public function incomingInvoices(): TenantIncomingInvoiceResource
    {
        return $this->incomingInvoices ??= new TenantIncomingInvoiceResource($this->http);
    }

    /**
     * Resource pour les operations sur factures (tous types).
     *
     * Endpoints disponibles:
     * - POST /tenant/sub-tenants/{id}/invoices - Creer une facture pour un sub-tenant
     * - GET /tenant/sub-tenants/{id}/invoices - Lister les factures d'un sub-tenant
     * - GET /tenant/invoices/{id} - Recuperer une facture
     * - PUT /tenant/invoices/{id} - Modifier un brouillon
     * - DELETE /tenant/invoices/{id} - Supprimer un brouillon
     * - POST /tenant/invoices/{id}/submit - Soumettre pour traitement
     * - GET /tenant/invoices/{id}/status - Verifier le statut
     * - GET /tenant/invoices/{id}/remaining-creditable - Montants restants creditables
     *
     * @example
     * ```php
     * // Creer une facture pour un sub-tenant
     * $invoice = $tenant->invoices()->createForSubTenant('sub-tenant-uuid', [...]);
     *
     * // Modifier un brouillon
     * $invoice = $tenant->invoices()->update('invoice-uuid', [
     *     'due_date' => '2026-02-26',
     * ]);
     *
     * // Supprimer un brouillon
     * $tenant->invoices()->delete('invoice-uuid');
     *
     * // Soumettre pour traitement
     * $tenant->invoices()->submit('invoice-uuid');
     * ```
     */
    public function invoices(): TenantInvoiceResource
    {
        return $this->invoices ??= new TenantInvoiceResource($this->http);
    }

    /**
     * Resource pour les operations sur avoirs (credit notes).
     *
     * Endpoints disponibles:
     * - POST /tenant/sub-tenants/{id}/credit-notes - Creer un avoir pour un sub-tenant
     * - GET /tenant/sub-tenants/{id}/credit-notes - Lister les avoirs d'un sub-tenant
     * - GET /tenant/credit-notes/{id} - Recuperer un avoir
     * - PUT /tenant/credit-notes/{id} - Modifier un brouillon
     * - DELETE /tenant/credit-notes/{id} - Supprimer un brouillon
     * - POST /tenant/credit-notes/{id}/send - Envoyer l'avoir
     * - GET /tenant/credit-notes/{id}/download - Telecharger le PDF
     *
     * @example
     * ```php
     * // Creer un avoir pour un sub-tenant
     * $creditNote = $tenant->creditNotes()->createForSubTenant('sub-tenant-uuid', [...]);
     *
     * // Modifier un brouillon
     * $creditNote = $tenant->creditNotes()->update('credit-note-uuid', [...]);
     *
     * // Supprimer un brouillon
     * $tenant->creditNotes()->delete('credit-note-uuid');
     *
     * // Envoyer l'avoir
     * $tenant->creditNotes()->send('credit-note-uuid');
     *
     * // Telecharger le PDF
     * $pdf = $tenant->creditNotes()->download('credit-note-uuid');
     * ```
     */
    public function creditNotes(): TenantCreditNoteResource
    {
        return $this->creditNotes ??= new TenantCreditNoteResource($this->http);
    }

    /**
     * Resource pour la conformite fiscale (LF 2026).
     */
    public function fiscal(): FiscalResource
    {
        return $this->fiscal ??= new FiscalResource($this->http);
    }

    /**
     * Resource pour la facturation consolidee.
     */
    public function billing(): BillingResource
    {
        return $this->billing ??= new BillingResource($this->http);
    }

    /**
     * Resource pour les statistiques detaillees.
     */
    public function detailedStats(): StatsResource
    {
        return $this->detailedStats ??= new StatsResource($this->http);
    }

    /**
     * Recupere les informations du tenant connecte.
     *
     * @return array{data: array}
     *
     * @example
     * ```php
     * $me = $tenant->me();
     * echo $me['data']['name'];
     * ```
     */
    public function me(): array
    {
        return $this->http->get('tenant/me');
    }

    /**
     * Met a jour les informations du tenant.
     *
     * @param array{name?: string, email?: string, phone?: string, ...} $data
     * @return array{data: array, message?: string}
     *
     * @example
     * ```php
     * $tenant->update(['name' => 'Nouveau Nom']);
     * ```
     */
    public function update(array $data): array
    {
        return $this->http->put('tenant/me', $data);
    }

    /**
     * Recupere le solde du tenant.
     *
     * @return array{data: array}
     *
     * @example
     * ```php
     * $balance = $tenant->balance();
     * echo "Solde: {$balance['data']['credits']} credits";
     * ```
     */
    public function balance(): array
    {
        return $this->http->get('tenant/balance');
    }

    /**
     * Recupere les statistiques du tenant.
     *
     * @return array{data: array}
     *
     * @example
     * ```php
     * $stats = $tenant->stats();
     * echo "Factures ce mois: {$stats['data']['invoices_this_month']}";
     * ```
     */
    public function stats(): array
    {
        return $this->http->get('tenant/stats');
    }

    /**
     * Regenere la cle tenant (attention: l'ancienne cle sera invalidee).
     *
     * @return array{data: array{tenant_key: string}, message?: string}
     *
     * @example
     * ```php
     * $result = $tenant->regenerateKey();
     * $newKey = $result['data']['tenant_key'];
     * // IMPORTANT: Mettez a jour votre configuration avec la nouvelle cle
     * ```
     */
    public function regenerateKey(): array
    {
        return $this->http->post('tenant/regenerate-key');
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
