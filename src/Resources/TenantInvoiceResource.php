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
 * Resource pour les operations sur les factures des sub-tenants.
 *
 * Permet de creer, modifier, supprimer et gerer les factures
 * associees a un sub-tenant.
 *
 * @example
 * ```php
 * $resource = new TenantInvoiceResource($httpClient);
 *
 * // Creer une facture pour un sub-tenant
 * $invoice = $resource->createForSubTenant('sub-tenant-uuid', [...]);
 *
 * // Modifier un brouillon
 * $invoice = $resource->update('invoice-uuid', ['due_date' => '2026-03-01']);
 *
 * // Soumettre pour traitement
 * $resource->submit('invoice-uuid');
 * ```
 */
class TenantInvoiceResource
{
    public function __construct(
        private readonly HttpClient $http
    ) {}

    /**
     * Liste les factures d'un sub-tenant.
     *
     * @param string $subTenantId UUID du sub-tenant
     * @param array{
     *     search?: string,
     *     status?: string,
     *     direction?: Direction|string,
     *     date_from?: DateTimeInterface|string,
     *     date_to?: DateTimeInterface|string,
     *     per_page?: int,
     *     page?: int,
     *     sort?: string,
     *     order?: string
     * } $filters Filtres optionnels
     * @return PaginatedResult<Invoice>
     *
     * @example
     * ```php
     * $invoices = $resource->listForSubTenant('sub-tenant-uuid', [
     *     'status' => 'validated',
     *     'per_page' => 25,
     * ]);
     * ```
     */
    public function listForSubTenant(string $subTenantId, array $filters = []): PaginatedResult
    {
        $query = $this->normalizeFilters($filters);
        $response = $this->http->get("tenant/sub-tenants/{$subTenantId}/invoices", $query);

        return PaginatedResult::fromArray($response, fn(array $data) => Invoice::fromArray($data));
    }

    /**
     * Cree une facture pour un sub-tenant.
     *
     * @param string $subTenantId UUID du sub-tenant
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
     * $invoice = $resource->createForSubTenant('sub-tenant-uuid', [
     *     'direction' => 'outgoing',
     *     'output_format' => 'facturx',
     *     'issue_date' => '2026-01-26',
     *     'seller' => [...],
     *     'buyer' => [...],
     *     'lines' => [...],
     * ]);
     * ```
     */
    public function createForSubTenant(string $subTenantId, array $data): Invoice
    {
        $payload = $this->normalizeCreatePayload($data);
        $response = $this->http->post("tenant/sub-tenants/{$subTenantId}/invoices", $payload);

        return Invoice::fromArray($response['data']);
    }

    /**
     * Recupere une facture par son ID.
     *
     * @param string $invoiceId UUID de la facture
     *
     * @example
     * ```php
     * $invoice = $resource->get('invoice-uuid');
     * echo "Statut: {$invoice->status->value}";
     * ```
     */
    public function get(string $invoiceId): Invoice
    {
        $response = $this->http->get("tenant/invoices/{$invoiceId}");

        return Invoice::fromArray($response['data']);
    }

    /**
     * Modifie une facture en brouillon.
     *
     * Seules les factures au statut 'draft' peuvent etre modifiees.
     *
     * @param string $invoiceId UUID de la facture
     * @param array{
     *     issue_date?: DateTimeInterface|string,
     *     due_date?: DateTimeInterface|string,
     *     seller?: array,
     *     buyer?: array,
     *     lines?: array[],
     *     currency?: string,
     *     external_id?: string,
     *     metadata?: array
     * } $data Donnees a modifier
     *
     * @example
     * ```php
     * // Modifier la date d'echeance
     * $invoice = $resource->update('invoice-uuid', [
     *     'due_date' => '2026-03-01',
     * ]);
     *
     * // Modifier les lignes
     * $invoice = $resource->update('invoice-uuid', [
     *     'lines' => [
     *         ['description' => 'Nouvelle prestation', 'quantity' => 1, 'unit_price' => 500, 'tax_rate' => 20],
     *     ],
     * ]);
     * ```
     */
    public function update(string $invoiceId, array $data): Invoice
    {
        $payload = $this->normalizeUpdatePayload($data);
        $response = $this->http->put("tenant/invoices/{$invoiceId}", $payload);

        return Invoice::fromArray($response['data']);
    }

    /**
     * Supprime une facture en brouillon.
     *
     * Seules les factures au statut 'draft' peuvent etre supprimees.
     *
     * @param string $invoiceId UUID de la facture
     *
     * @example
     * ```php
     * $resource->delete('invoice-uuid');
     * ```
     */
    public function delete(string $invoiceId): void
    {
        $this->http->delete("tenant/invoices/{$invoiceId}");
    }

    /**
     * Soumet une facture pour traitement.
     *
     * Lance le processus de validation et transmission de la facture.
     *
     * @param string $invoiceId UUID de la facture
     * @return array{data: array, message?: string}
     *
     * @example
     * ```php
     * $result = $resource->submit('invoice-uuid');
     * echo "Facture soumise: {$result['message']}";
     * ```
     */
    public function submit(string $invoiceId): array
    {
        return $this->http->post("tenant/invoices/{$invoiceId}/submit");
    }

    /**
     * Recupere le statut detaille d'une facture.
     *
     * @param string $invoiceId UUID de la facture
     * @return array{data: array{status: string, message?: string, updated_at: string}}
     *
     * @example
     * ```php
     * $status = $resource->status('invoice-uuid');
     * echo "Statut: {$status['data']['status']}";
     * ```
     */
    public function status(string $invoiceId): array
    {
        return $this->http->get("tenant/invoices/{$invoiceId}/status");
    }

    /**
     * Recupere les montants restants creditables pour une facture.
     *
     * Utile pour connaitre les montants disponibles pour un avoir partiel.
     *
     * @param string $invoiceId UUID de la facture
     * @return array{data: array{total_ht: float, total_tax: float, total_ttc: float, lines: array}}
     *
     * @example
     * ```php
     * $remaining = $resource->remainingCreditable('invoice-uuid');
     * echo "Montant restant creditable: {$remaining['data']['total_ttc']} EUR";
     * ```
     */
    public function remainingCreditable(string $invoiceId): array
    {
        return $this->http->get("tenant/invoices/{$invoiceId}/remaining-creditable");
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

    /**
     * Normalise le payload de mise a jour.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeUpdatePayload(array $data): array
    {
        $payload = [];

        // Dates
        if (isset($data['issue_date'])) {
            $payload['issue_date'] = $data['issue_date'] instanceof DateTimeInterface
                ? $data['issue_date']->format('Y-m-d')
                : $data['issue_date'];
        }
        if (isset($data['due_date'])) {
            $payload['due_date'] = $data['due_date'] instanceof DateTimeInterface
                ? $data['due_date']->format('Y-m-d')
                : $data['due_date'];
        }

        // Autres champs
        foreach (['seller', 'buyer', 'lines', 'currency', 'external_id', 'metadata'] as $field) {
            if (isset($data[$field])) {
                $payload[$field] = $data[$field];
            }
        }

        return $payload;
    }
}
