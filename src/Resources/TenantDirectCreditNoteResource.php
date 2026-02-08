<?php

declare(strict_types=1);

namespace Scell\Sdk\Resources;

use DateTimeInterface;
use Scell\Sdk\DTOs\CreditNote;
use Scell\Sdk\DTOs\PaginatedResult;
use Scell\Sdk\Http\HttpClient;

/**
 * Resource pour les avoirs directs du tenant (sans sub-tenant).
 *
 * Permet de creer et lister des avoirs directement pour le tenant,
 * sans passer par un sub-tenant.
 *
 * @example
 * ```php
 * $resource = new TenantDirectCreditNoteResource($httpClient);
 *
 * // Creer un avoir direct (partiel)
 * $creditNote = $resource->create([
 *     'invoice_id' => 'uuid-facture-origine',
 *     'reason' => 'Remboursement partiel - Article defectueux',
 *     'type' => 'partial',
 *     'items' => [
 *         ['description' => 'Article defectueux', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 20],
 *     ],
 * ]);
 *
 * // Lister les avoirs
 * $creditNotes = $resource->list(['status' => 'draft']);
 * ```
 */
class TenantDirectCreditNoteResource
{
    public function __construct(
        private readonly HttpClient $http
    ) {}

    /**
     * Liste les avoirs du tenant avec filtres et pagination.
     *
     * @param array{
     *     search?: string,
     *     status?: string,
     *     date_from?: DateTimeInterface|string,
     *     date_to?: DateTimeInterface|string,
     *     invoice_id?: string,
     *     min_amount?: float,
     *     max_amount?: float,
     *     per_page?: int,
     *     page?: int,
     *     sort?: string,
     *     order?: string
     * } $filters Filtres optionnels
     * @return PaginatedResult<CreditNote>
     *
     * @example
     * ```php
     * // Liste simple
     * $creditNotes = $resource->list();
     *
     * // Avec filtres
     * $creditNotes = $resource->list([
     *     'status' => 'draft,validated',
     *     'date_from' => '2026-01-01',
     *     'per_page' => 50,
     * ]);
     *
     * // Par facture d'origine
     * $creditNotes = $resource->list(['invoice_id' => 'uuid-facture']);
     * ```
     */
    public function list(array $filters = []): PaginatedResult
    {
        $query = $this->normalizeFilters($filters);
        $response = $this->http->get('tenant/credit-notes', $query);

        return PaginatedResult::fromArray($response, fn(array $data) => CreditNote::fromArray($data));
    }

    /**
     * Cree un nouvel avoir direct.
     *
     * L'avoir est cree en brouillon. Utilisez la methode `send()` pour l'envoyer.
     *
     * @param array{
     *     invoice_id: string,
     *     reason: string,
     *     type: string,
     *     items?: array[],
     *     external_id?: string,
     *     metadata?: array
     * } $data Donnees de l'avoir
     *
     * Types d'avoir:
     * - `full`: Avoir total (annulation complete de la facture)
     * - `partial`: Avoir partiel (remboursement d'une partie)
     * - `discount`: Remise commerciale
     *
     * @example
     * ```php
     * // Avoir total
     * $creditNote = $resource->create([
     *     'invoice_id' => 'uuid-facture-origine',
     *     'reason' => 'Annulation de commande',
     *     'type' => 'full',
     * ]);
     *
     * // Avoir partiel avec items specifiques
     * $creditNote = $resource->create([
     *     'invoice_id' => 'uuid-facture-origine',
     *     'reason' => 'Remboursement article defectueux',
     *     'type' => 'partial',
     *     'items' => [
     *         [
     *             'description' => 'Article defectueux - ref ABC123',
     *             'quantity' => 2,
     *             'unit_price' => 50.00,
     *             'tax_rate' => 20.0,
     *         ],
     *     ],
     * ]);
     *
     * // Remise commerciale
     * $creditNote = $resource->create([
     *     'invoice_id' => 'uuid-facture-origine',
     *     'reason' => 'Remise exceptionnelle client fidele',
     *     'type' => 'discount',
     *     'items' => [
     *         [
     *             'description' => 'Remise 10%',
     *             'quantity' => 1,
     *             'unit_price' => 100.00,
     *             'tax_rate' => 20.0,
     *         ],
     *     ],
     * ]);
     * ```
     */
    public function create(array $data): CreditNote
    {
        $response = $this->http->post('tenant/credit-notes', $data);

        return CreditNote::fromArray($response['data']);
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

            if ($value instanceof DateTimeInterface) {
                $query[$key] = $value->format('Y-m-d');
            } else {
                $query[$key] = $value;
            }
        }

        return $query;
    }
}
