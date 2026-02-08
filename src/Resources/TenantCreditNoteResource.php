<?php

declare(strict_types=1);

namespace Scell\Sdk\Resources;

use DateTimeInterface;
use Scell\Sdk\DTOs\CreditNote;
use Scell\Sdk\DTOs\PaginatedResult;
use Scell\Sdk\Http\HttpClient;

/**
 * Resource pour les avoirs (credit notes) des sub-tenants.
 *
 * Permet de creer, lister et gerer les avoirs pour les clients finaux
 * d'un tenant partenaire.
 *
 * @example
 * ```php
 * $resource = new TenantCreditNoteResource($httpClient);
 *
 * // Creer un avoir pour un sub-tenant
 * $creditNote = $resource->createForSubTenant('sub-tenant-uuid', [
 *     'invoice_id' => 'uuid-facture',
 *     'reason' => 'Remboursement partiel',
 *     'type' => 'partial',
 *     'items' => [...],
 * ]);
 *
 * // Modifier un brouillon
 * $creditNote = $resource->update('credit-note-uuid', ['reason' => 'Nouvelle raison']);
 *
 * // Envoyer l'avoir
 * $resource->send('credit-note-uuid');
 * ```
 */
class TenantCreditNoteResource
{
    public function __construct(
        private readonly HttpClient $http
    ) {}

    /**
     * Liste les avoirs d'un sub-tenant.
     *
     * @param string $subTenantId UUID du sub-tenant
     * @param array{
     *     search?: string,
     *     status?: string,
     *     date_from?: DateTimeInterface|string,
     *     date_to?: DateTimeInterface|string,
     *     invoice_id?: string,
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
     * $creditNotes = $resource->listForSubTenant('sub-tenant-uuid');
     *
     * // Avec filtres
     * $creditNotes = $resource->listForSubTenant('sub-tenant-uuid', [
     *     'status' => 'draft',
     *     'per_page' => 50,
     * ]);
     * ```
     */
    public function listForSubTenant(string $subTenantId, array $filters = []): PaginatedResult
    {
        $query = $this->normalizeFilters($filters);
        $response = $this->http->get("tenant/sub-tenants/{$subTenantId}/credit-notes", $query);

        return PaginatedResult::fromArray($response, fn(array $data) => CreditNote::fromArray($data));
    }

    /**
     * Cree un avoir pour un sub-tenant.
     *
     * @param string $subTenantId UUID du sub-tenant
     * @param array{
     *     invoice_id: string,
     *     reason: string,
     *     type: string,
     *     items?: array[],
     *     external_id?: string,
     *     metadata?: array
     * } $data Donnees de l'avoir
     *
     * @example
     * ```php
     * // Avoir total
     * $creditNote = $resource->createForSubTenant('sub-tenant-uuid', [
     *     'invoice_id' => 'uuid-facture',
     *     'reason' => 'Annulation de commande',
     *     'type' => 'full',
     * ]);
     *
     * // Avoir partiel
     * $creditNote = $resource->createForSubTenant('sub-tenant-uuid', [
     *     'invoice_id' => 'uuid-facture',
     *     'reason' => 'Article retourne',
     *     'type' => 'partial',
     *     'items' => [
     *         ['description' => 'Article ABC', 'quantity' => 1, 'unit_price' => 50, 'tax_rate' => 20],
     *     ],
     * ]);
     * ```
     */
    public function createForSubTenant(string $subTenantId, array $data): CreditNote
    {
        $response = $this->http->post("tenant/sub-tenants/{$subTenantId}/credit-notes", $data);

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
     * echo "Statut: {$creditNote->statusLabel()}";
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
     * // Modifier la raison
     * $creditNote = $resource->update('credit-note-uuid', [
     *     'reason' => 'Nouvelle raison du remboursement',
     * ]);
     *
     * // Modifier les items
     * $creditNote = $resource->update('credit-note-uuid', [
     *     'items' => [
     *         ['description' => 'Nouvel item', 'quantity' => 2, 'unit_price' => 25, 'tax_rate' => 20],
     *     ],
     * ]);
     * ```
     */
    public function update(string $creditNoteId, array $data): CreditNote
    {
        $response = $this->http->put("tenant/credit-notes/{$creditNoteId}", $data);

        return CreditNote::fromArray($response['data']);
    }

    /**
     * Supprime un avoir en brouillon.
     *
     * Seuls les avoirs au statut 'draft' peuvent etre supprimes.
     *
     * @param string $creditNoteId UUID de l'avoir
     *
     * @example
     * ```php
     * $resource->delete('credit-note-uuid');
     * ```
     */
    public function delete(string $creditNoteId): void
    {
        $this->http->delete("tenant/credit-notes/{$creditNoteId}");
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
     *
     * // Afficher les lignes creditables
     * foreach ($remaining['data']['lines'] as $line) {
     *     echo "{$line['description']}: {$line['remaining_ttc']} EUR";
     * }
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

    // =========================================================================
    // DEPRECATED METHODS - For backward compatibility
    // =========================================================================

    /**
     * @deprecated Use listForSubTenant() instead
     */
    public function list(string $subTenantId, array $params = []): array
    {
        return $this->http->get("tenant/sub-tenants/{$subTenantId}/credit-notes", $params);
    }

    /**
     * @deprecated Use createForSubTenant() instead
     */
    public function create(string $subTenantId, array $data): array
    {
        return $this->http->post("tenant/sub-tenants/{$subTenantId}/credit-notes", $data);
    }
}
