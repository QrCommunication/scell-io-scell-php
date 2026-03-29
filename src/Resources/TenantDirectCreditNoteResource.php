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
     * Recupere un avoir par son ID.
     *
     * @param string $creditNoteId UUID de l'avoir
     *
     * @example
     * ```php
     * $creditNote = $resource->get('credit-note-uuid');
     * echo "Avoir: {$creditNote->creditNoteNumber}";
     * ```
     */
    public function get(string $creditNoteId): CreditNote
    {
        $response = $this->http->get("tenant/credit-notes/{$creditNoteId}");

        return CreditNote::fromArray($response['data']);
    }

    /**
     * Modifie un avoir en brouillon.
     *
     * Seuls les avoirs au statut 'draft' peuvent etre modifies.
     *
     * @param string $creditNoteId UUID de l'avoir
     * @param array{
     *     reason?: string,
     *     items?: array[],
     *     external_id?: string,
     *     metadata?: array
     * } $data Donnees a modifier
     *
     * @example
     * ```php
     * $creditNote = $resource->update('credit-note-uuid', [
     *     'reason' => 'Nouvelle raison du remboursement',
     * ]);
     * ```
     */
    public function update(string $creditNoteId, array $data): CreditNote
    {
        $response = $this->http->put("tenant/credit-notes/{$creditNoteId}", $data);

        return CreditNote::fromArray($response['data']);
    }

    /**
     * Envoie (valide et transmet) un avoir.
     *
     * Cette action finalise l'avoir et le transmet au destinataire.
     * L'avoir ne pourra plus etre modifie apres cette action.
     *
     * @param string $creditNoteId UUID de l'avoir
     * @return array{data: array, message?: string}
     *
     * @example
     * ```php
     * $result = $resource->send('credit-note-uuid');
     * echo "Avoir envoye: {$result['message']}";
     * ```
     */
    public function send(string $creditNoteId): array
    {
        return $this->http->post("tenant/credit-notes/{$creditNoteId}/send");
    }

    /**
     * Telecharge le PDF d'un avoir.
     *
     * @param string $creditNoteId UUID de l'avoir
     * @return string Contenu binaire du PDF
     *
     * @example
     * ```php
     * $pdf = $resource->download('credit-note-uuid');
     * file_put_contents('avoir.pdf', $pdf);
     * ```
     */
    public function download(string $creditNoteId): string
    {
        return $this->http->getRaw("tenant/credit-notes/{$creditNoteId}/download");
    }

    /**
     * Recupere les montants restants creditables pour une facture.
     *
     * @param string $invoiceId UUID de la facture
     * @return array{data: array{total_ht: float, total_tax: float, total_ttc: float, lines: array}}
     *
     * @example
     * ```php
     * $remaining = $resource->remainingCreditable('invoice-uuid');
     * echo "Montant restant: {$remaining['data']['total_ttc']} EUR";
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

            if ($value instanceof DateTimeInterface) {
                $query[$key] = $value->format('Y-m-d');
            } else {
                $query[$key] = $value;
            }
        }

        return $query;
    }
}
