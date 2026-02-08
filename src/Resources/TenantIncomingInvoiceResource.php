<?php

declare(strict_types=1);

namespace Scell\Sdk\Resources;

use DateTimeInterface;
use Scell\Sdk\DTOs\Invoice;
use Scell\Sdk\DTOs\PaginatedResult;
use Scell\Sdk\Enums\InvoiceStatus;
use Scell\Sdk\Enums\RejectionCode;
use Scell\Sdk\Http\HttpClient;

/**
 * Resource pour les factures entrantes (fournisseurs).
 *
 * Permet de gerer les factures recues des fournisseurs dans le cadre
 * de la facturation electronique obligatoire (Factur-X, UBL, CII).
 *
 * Cycle de vie typique:
 * 1. Reception de la facture (status: received)
 * 2. Acceptation, rejet ou contestation
 * 3. Paiement et marquage comme payee
 *
 * @example
 * ```php
 * $resource = new TenantIncomingInvoiceResource($httpClient);
 *
 * // Creer une facture entrante manuellement
 * $invoice = $resource->create('sub-tenant-uuid', [
 *     'invoice_number' => 'FOURN-2026-001',
 *     'issue_date' => '2026-01-26',
 *     'seller' => [...],  // Le fournisseur
 *     'buyer' => [...],   // Votre client (sub-tenant)
 *     'lines' => [...],
 * ]);
 *
 * // Accepter une facture
 * $resource->accept('invoice-uuid');
 *
 * // Marquer comme payee
 * $resource->markPaid('invoice-uuid', 'VIR-2026-001');
 * ```
 */
class TenantIncomingInvoiceResource
{
    public function __construct(
        private readonly HttpClient $http
    ) {}

    /**
     * Liste les factures entrantes d'un sub-tenant.
     *
     * @param string $subTenantId UUID du sub-tenant
     * @param array{
     *     search?: string,
     *     status?: string,
     *     date_from?: DateTimeInterface|string,
     *     date_to?: DateTimeInterface|string,
     *     seller_siret?: string,
     *     min_amount?: float,
     *     max_amount?: float,
     *     per_page?: int,
     *     page?: int,
     *     sort?: string,
     *     order?: string
     * } $filters Filtres optionnels
     * @return PaginatedResult<Invoice>
     *
     * @example
     * ```php
     * // Lister toutes les factures entrantes
     * $incoming = $resource->listForSubTenant('sub-tenant-uuid');
     *
     * // Filtrer par statut
     * $pending = $resource->listForSubTenant('sub-tenant-uuid', [
     *     'status' => 'received',
     *     'per_page' => 50,
     * ]);
     *
     * // Filtrer par fournisseur
     * $fromSupplier = $resource->listForSubTenant('sub-tenant-uuid', [
     *     'seller_siret' => '12345678901234',
     * ]);
     * ```
     */
    public function listForSubTenant(string $subTenantId, array $filters = []): PaginatedResult
    {
        $query = $this->normalizeFilters($filters);
        $response = $this->http->get("tenant/sub-tenants/{$subTenantId}/invoices/incoming", $query);

        return PaginatedResult::fromArray($response, fn(array $data) => Invoice::fromArray($data));
    }

    /**
     * Cree une facture entrante pour un sub-tenant.
     *
     * Utilisez cette methode pour enregistrer manuellement une facture fournisseur.
     * Cette operation est gratuite (pas de debit de credits).
     *
     * @param string $subTenantId UUID du sub-tenant
     * @param array{
     *     invoice_number: string,
     *     output_format?: string,
     *     issue_date: DateTimeInterface|string,
     *     seller: array{siret: string, name: string, address: array},
     *     buyer: array{siret: string, name: string, address: array},
     *     lines: array[],
     *     due_date?: DateTimeInterface|string,
     *     currency?: string,
     *     external_id?: string,
     *     metadata?: array
     * } $data Donnees de la facture
     *
     * @example
     * ```php
     * $invoice = $resource->create('sub-tenant-uuid', [
     *     'invoice_number' => 'FOURN-2026-001',
     *     'output_format' => 'facturx',
     *     'issue_date' => '2026-01-26',
     *     'due_date' => '2026-02-26',
     *     'seller' => [  // Le fournisseur
     *         'siret' => '11111111111111',
     *         'name' => 'Fournisseur SA',
     *         'address' => [
     *             'line1' => '5 rue du Commerce',
     *             'postal_code' => '69001',
     *             'city' => 'Lyon',
     *             'country' => 'FR',
     *         ],
     *     ],
     *     'buyer' => [  // Votre client (sub-tenant)
     *         'siret' => '22222222222222',
     *         'name' => 'Mon Client SARL',
     *         'address' => [
     *             'line1' => '1 rue de la Paix',
     *             'postal_code' => '75001',
     *             'city' => 'Paris',
     *             'country' => 'FR',
     *         ],
     *     ],
     *     'lines' => [
     *         [
     *             'description' => 'Fournitures de bureau',
     *             'quantity' => 10,
     *             'unit_price' => 50.00,
     *             'tax_rate' => 20.0,
     *         ],
     *     ],
     * ]);
     * ```
     */
    public function create(string $subTenantId, array $data): Invoice
    {
        $payload = $this->normalizeCreatePayload($data);
        $response = $this->http->post("tenant/sub-tenants/{$subTenantId}/invoices/incoming", $payload);

        return Invoice::fromArray($response['data']);
    }

    /**
     * Recupere une facture entrante par son ID.
     *
     * @param string $invoiceId UUID de la facture
     *
     * @example
     * ```php
     * $invoice = $resource->get('invoice-uuid');
     * echo "Facture du fournisseur: {$invoice->sellerName}";
     * echo "Statut: {$invoice->status->value}";
     * ```
     */
    public function get(string $invoiceId): Invoice
    {
        $response = $this->http->get("tenant/invoices/incoming/{$invoiceId}");

        return Invoice::fromArray($response['data']);
    }

    /**
     * Accepte une facture entrante.
     *
     * Confirme que la facture est conforme et sera payee.
     *
     * @param string $invoiceId UUID de la facture
     * @param array{comment?: string, metadata?: array} $data Donnees optionnelles
     *
     * @example
     * ```php
     * // Acceptation simple
     * $invoice = $resource->accept('invoice-uuid');
     *
     * // Avec commentaire
     * $invoice = $resource->accept('invoice-uuid', [
     *     'comment' => 'Facture conforme au bon de commande',
     * ]);
     * ```
     */
    public function accept(string $invoiceId, array $data = []): Invoice
    {
        $response = $this->http->post("tenant/invoices/incoming/{$invoiceId}/accept", $data);

        return Invoice::fromArray($response['data']);
    }

    /**
     * Rejette une facture entrante.
     *
     * La facture est refusee et ne sera pas payee.
     *
     * @param string $invoiceId UUID de la facture
     * @param string $reason Motif du rejet (obligatoire)
     * @param RejectionCode|string|null $code Code de rejet standardise (optionnel)
     *
     * @example
     * ```php
     * // Rejet simple
     * $invoice = $resource->reject('invoice-uuid', 'Montant incorrect');
     *
     * // Avec code de rejet
     * $invoice = $resource->reject(
     *     'invoice-uuid',
     *     'Le montant facture ne correspond pas au devis',
     *     RejectionCode::AmountError
     * );
     *
     * // Ou avec code en string
     * $invoice = $resource->reject('invoice-uuid', 'Article non conforme', 'PRODUCT_ERROR');
     * ```
     */
    public function reject(string $invoiceId, string $reason, RejectionCode|string|null $code = null): Invoice
    {
        $payload = ['reason' => $reason];

        if ($code !== null) {
            $payload['reason_code'] = $code instanceof RejectionCode ? $code->value : $code;
        }

        $response = $this->http->post("tenant/invoices/incoming/{$invoiceId}/reject", $payload);

        return Invoice::fromArray($response['data']);
    }

    /**
     * Marque une facture entrante comme payee.
     *
     * Cette action finalise le cycle de vie de la facture dans le contexte
     * de la facturation electronique obligatoire.
     *
     * @param string $invoiceId UUID de la facture
     * @param string|null $reference Reference de paiement (virement, cheque, etc.)
     * @param array{paid_at?: string, note?: string} $data Donnees optionnelles
     *
     * @example
     * ```php
     * // Marquer comme payee avec reference
     * $invoice = $resource->markPaid('invoice-uuid', 'VIR-2026-001234');
     *
     * // Avec date et note
     * $invoice = $resource->markPaid('invoice-uuid', 'VIR-2026-001234', [
     *     'paid_at' => '2026-01-28',
     *     'note' => 'Paiement par virement SEPA',
     * ]);
     * ```
     */
    public function markPaid(string $invoiceId, ?string $reference = null, array $data = []): Invoice
    {
        $payload = $data;

        if ($reference !== null) {
            $payload['payment_reference'] = $reference;
        }

        $response = $this->http->post("tenant/invoices/incoming/{$invoiceId}/mark-paid", $payload);

        return Invoice::fromArray($response['data']);
    }

    /**
     * Normalise les filtres de liste.
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function normalizeFilters(array $filters): array
    {
        $query = [];

        foreach ($filters as $key => $value) {
            if ($value === null) {
                continue;
            }

            if ($value instanceof InvoiceStatus) {
                $query[$key] = $value->value;
            } elseif ($value instanceof DateTimeInterface) {
                $query[$key] = $value->format('Y-m-d');
            } else {
                $query[$key] = $value;
            }
        }

        return $query;
    }

    /**
     * Normalise le payload de creation.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeCreatePayload(array $data): array
    {
        $payload = [];

        // Numero et format
        $payload['invoice_number'] = $data['invoice_number'];
        if (isset($data['output_format'])) {
            $payload['output_format'] = $data['output_format'];
        }

        // Dates
        $payload['issue_date'] = $data['issue_date'] instanceof DateTimeInterface
            ? $data['issue_date']->format('Y-m-d')
            : $data['issue_date'];

        if (isset($data['due_date'])) {
            $payload['due_date'] = $data['due_date'] instanceof DateTimeInterface
                ? $data['due_date']->format('Y-m-d')
                : $data['due_date'];
        }

        // Vendeur et acheteur
        $payload['seller'] = $data['seller'];
        $payload['buyer'] = $data['buyer'];

        // Lignes
        $payload['lines'] = $data['lines'];

        // Champs optionnels
        if (isset($data['currency'])) {
            $payload['currency'] = $data['currency'];
        }
        if (isset($data['external_id'])) {
            $payload['external_id'] = $data['external_id'];
        }
        if (isset($data['metadata'])) {
            $payload['metadata'] = $data['metadata'];
        }

        return $payload;
    }
}
