<?php

declare(strict_types=1);

namespace Scell\Sdk\Resources;

use DateTimeInterface;
use Scell\Sdk\DTOs\CreditNote;
use Scell\Sdk\DTOs\PaginatedResult;
use Scell\Sdk\Http\HttpClient;

/**
 * Resource pour les avoirs (credit notes) du dashboard.
 *
 * Permet de creer, lister et gerer les avoirs via Bearer token (utilisateur connecte).
 * La suppression est interdite (conformite ISCA).
 *
 * @example
 * ```php
 * $client = new ScellClient($bearerToken);
 *
 * // Lister les avoirs
 * $creditNotes = $client->creditNotes()->list(['status' => 'draft']);
 *
 * // Creer un avoir
 * $creditNote = $client->creditNotes()->create([
 *     'invoice_id' => 'uuid-facture',
 *     'reason' => 'Remboursement partiel',
 *     'type' => 'partial',
 *     'items' => [...],
 * ]);
 *
 * // Envoyer un avoir
 * $client->creditNotes()->send('credit-note-uuid');
 *
 * // Telecharger le PDF
 * $pdf = $client->creditNotes()->download('credit-note-uuid');
 * file_put_contents('avoir.pdf', $pdf);
 * ```
 */
class CreditNoteResource
{
    public function __construct(
        private readonly HttpClient $http
    ) {}

    /**
     * Liste les avoirs avec filtrage optionnel.
     *
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
     */
    public function list(array $filters = []): PaginatedResult
    {
        $query = $this->normalizeFilters($filters);
        $response = $this->http->get('credit-notes', $query);

        return PaginatedResult::fromArray($response, fn(array $data) => CreditNote::fromArray($data));
    }

    /**
     * Recupere un avoir par son ID.
     *
     * @param string $id UUID de l'avoir
     */
    public function get(string $id): CreditNote
    {
        $response = $this->http->get("credit-notes/{$id}");

        return CreditNote::fromArray($response['data']);
    }

    /**
     * Cree un nouvel avoir.
     *
     * @param array{
     *     invoice_id: string,
     *     reason: string,
     *     type: string,
     *     items?: array[],
     *     external_id?: string,
     *     metadata?: array
     * } $data Donnees de l'avoir
     */
    public function create(array $data): CreditNote
    {
        $response = $this->http->post('credit-notes', $data);

        return CreditNote::fromArray($response['data']);
    }

    /**
     * Envoie (valide et transmet) un avoir.
     *
     * Cette action finalise l'avoir et le transmet au destinataire.
     * L'avoir ne pourra plus etre modifie apres cette action.
     *
     * @param string $id UUID de l'avoir
     * @return array{data: array, message?: string}
     */
    public function send(string $id): array
    {
        return $this->http->post("credit-notes/{$id}/send");
    }

    /**
     * Telecharge le PDF d'un avoir.
     *
     * @param string $id UUID de l'avoir
     * @return string Contenu binaire du PDF
     */
    public function download(string $id): string
    {
        return $this->http->getRaw("credit-notes/{$id}/download");
    }

    /**
     * Recupere les montants restants creditables pour une facture.
     *
     * @param string $invoiceId UUID de la facture
     * @return array{data: array{total_ht: float, total_tax: float, total_ttc: float, lines: array}}
     */
    public function remainingCreditable(string $invoiceId): array
    {
        return $this->http->get("invoices/{$invoiceId}/remaining-creditable");
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
