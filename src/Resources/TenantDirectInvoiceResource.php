<?php

declare(strict_types=1);

namespace Scell\Sdk\Resources;

use DateTimeInterface;
use Scell\Sdk\DTOs\Invoice;
use Scell\Sdk\DTOs\PaginatedResult;
use Scell\Sdk\Enums\Direction;
use Scell\Sdk\Enums\InvoiceStatus;
use Scell\Sdk\Enums\OutputFormat;
use Scell\Sdk\Http\HttpClient;

/**
 * Resource pour les factures directes du tenant (sans sub-tenant).
 *
 * Permet de creer et lister des factures directement pour le tenant,
 * sans passer par un sub-tenant.
 *
 * @example
 * ```php
 * $resource = new TenantDirectInvoiceResource($httpClient);
 *
 * // Creer une facture directe
 * $invoice = $resource->create([
 *     'direction' => 'outgoing',
 *     'output_format' => 'facturx',
 *     'issue_date' => '2026-01-26',
 *     'seller' => [...],
 *     'buyer' => [...],
 *     'lines' => [...],
 * ]);
 *
 * // Lister les factures
 * $invoices = $resource->list(['status' => 'validated']);
 * ```
 */
class TenantDirectInvoiceResource
{
    public function __construct(
        private readonly HttpClient $http
    ) {}

    /**
     * Liste les factures du tenant avec filtres et pagination.
     *
     * @param array{
     *     search?: string,
     *     status?: string,
     *     direction?: Direction|string,
     *     date_from?: DateTimeInterface|string,
     *     date_to?: DateTimeInterface|string,
     *     buyer_siret?: string,
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
     * // Liste simple
     * $invoices = $resource->list();
     *
     * // Avec filtres multiples
     * $invoices = $resource->list([
     *     'status' => 'draft,validated',
     *     'direction' => 'outgoing',
     *     'date_from' => '2026-01-01',
     *     'date_to' => '2026-01-31',
     *     'min_amount' => 1000,
     *     'per_page' => 50,
     *     'sort' => 'issue_date',
     *     'order' => 'desc',
     * ]);
     *
     * // Recherche textuelle
     * $invoices = $resource->list(['search' => 'FACT-2026']);
     * ```
     */
    public function list(array $filters = []): PaginatedResult
    {
        $query = $this->normalizeFilters($filters);
        $response = $this->http->get('tenant/invoices', $query);

        return PaginatedResult::fromArray($response, fn(array $data) => Invoice::fromArray($data));
    }

    /**
     * Cree une nouvelle facture directe.
     *
     * La numerotation est automatique et geree par le systeme.
     *
     * @param array{
     *     direction: Direction|string,
     *     output_format: OutputFormat|string,
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
     * $invoice = $resource->create([
     *     'direction' => 'outgoing',
     *     'output_format' => 'facturx',
     *     'issue_date' => '2026-01-26',
     *     'due_date' => '2026-02-26',
     *     'seller' => [
     *         'siret' => '12345678901234',
     *         'name' => 'Ma Societe SARL',
     *         'address' => [
     *             'line1' => '1 rue de la Paix',
     *             'postal_code' => '75001',
     *             'city' => 'Paris',
     *             'country' => 'FR',
     *         ],
     *     ],
     *     'buyer' => [
     *         'siret' => '98765432109876',
     *         'name' => 'Client SA',
     *         'address' => [
     *             'line1' => '10 avenue des Champs',
     *             'postal_code' => '75008',
     *             'city' => 'Paris',
     *             'country' => 'FR',
     *         ],
     *     ],
     *     'lines' => [
     *         [
     *             'description' => 'Prestation de service',
     *             'quantity' => 1,
     *             'unit_price' => 1000.00,
     *             'tax_rate' => 20.0,
     *         ],
     *         [
     *             'description' => 'Support technique',
     *             'quantity' => 5,
     *             'unit_price' => 100.00,
     *             'tax_rate' => 20.0,
     *         ],
     *     ],
     *     'metadata' => [
     *         'project_id' => 'PRJ-001',
     *     ],
     * ]);
     *
     * // Le numero de facture est genere automatiquement
     * echo "Facture creee: {$invoice->invoiceNumber}";
     * ```
     */
    public function create(array $data): Invoice
    {
        $payload = $this->normalizeCreatePayload($data);
        $response = $this->http->post('tenant/invoices', $payload);

        return Invoice::fromArray($response['data']);
    }

    /**
     * Cree plusieurs factures en une seule requete.
     *
     * @param array $invoices Tableau de factures a creer
     * @return array{data: array, message?: string}
     */
    public function bulkCreate(array $invoices): array
    {
        return $this->http->post('tenant/invoices/bulk', ['invoices' => $invoices]);
    }

    /**
     * Soumet plusieurs factures en une seule requete.
     *
     * @param array $invoiceIds Tableau d'UUIDs de factures
     * @return array{data: array, message?: string}
     */
    public function bulkSubmit(array $invoiceIds): array
    {
        return $this->http->post('tenant/invoices/bulk-submit', ['invoice_ids' => $invoiceIds]);
    }

    /**
     * Recupere le statut de plusieurs factures.
     *
     * @param array $invoiceIds Tableau d'UUIDs de factures
     * @return array{data: array}
     */
    public function bulkStatus(array $invoiceIds): array
    {
        return $this->http->post('tenant/invoices/bulk-status', ['invoice_ids' => $invoiceIds]);
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

            if ($value instanceof Direction || $value instanceof InvoiceStatus) {
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

        // Direction et format
        $payload['direction'] = $data['direction'] instanceof Direction
            ? $data['direction']->value
            : $data['direction'];
        $payload['output_format'] = $data['output_format'] instanceof OutputFormat
            ? $data['output_format']->value
            : $data['output_format'];

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
