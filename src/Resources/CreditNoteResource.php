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
 * Brouillons modifiables et supprimables. Apres envoi, l'avoir est immutable (ISCA).
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
 *     // avoir partiel : sélectionner des lignes de la facture (TVA héritée par ligne)
 *     'items' => [['invoice_line_id' => 'uuid-ligne', 'quantity' => 1]],
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
     * Cree un avoir (credit note).
     *
     * Un avoir cible TOUJOURS une facture existante (`invoice_id`) et ne peut
     * jamais inventer de montants :
     *  - `type = 'total'`   : credite TOUTES les lignes de la facture (`items` ignore).
     *  - `type = 'partial'` : il faut **selectionner des lignes de la facture source**
     *    via `items[].invoice_line_id`. Le prix unitaire et le **taux de TVA exact de
     *    chaque ligne** sont herites (une facture peut meler 20 % / 5,5 % / exonere 0 %
     *    — chaque ligne creditee au bon taux). `quantity` optionnel (defaut = quantite
     *    restante de la ligne).
     *
     * Workflow : appeler d'abord {@see remainingCreditable()} pour connaitre les lignes
     * (et quantites) encore creditables, puis selectionner parmi elles.
     *
     * @param array{
     *     invoice_id: string,
     *     reason: string,
     *     type: 'partial'|'total',
     *     items?: array<int, array{invoice_line_id: string, quantity?: float|int}>,
     *     sub_tenant_id?: string
     * } $data Donnees de l'avoir
     *
     * @example
     * ```php
     * $creditable = $client->creditNotes()->remainingCreditable('uuid-facture');
     * $avoir = $client->creditNotes()->create([
     *     'invoice_id' => 'uuid-facture',
     *     'reason' => 'Retour partiel',
     *     'type' => 'partial',
     *     'items' => [
     *         ['invoice_line_id' => $creditable['data']['items'][0]['invoice_line_id'], 'quantity' => 1],
     *     ],
     * ]);
     * ```
     */
    public function create(array $data): CreditNote
    {
        $response = $this->http->post('credit-notes', $data);

        return CreditNote::fromArray($response['data']);
    }

    /**
     * Met a jour un avoir en brouillon.
     *
     * @param string $id UUID de l'avoir
     * @param array<string, mixed> $data Donnees partielles a mettre a jour
     */
    public function update(string $id, array $data): CreditNote
    {
        $response = $this->http->put("credit-notes/{$id}", $data);
        return CreditNote::fromArray($response['data']);
    }

    /**
     * Supprime un avoir en brouillon.
     *
     * Seuls les avoirs en statut `draft` peuvent etre supprimes.
     *
     * @param string $id UUID de l'avoir
     */
    public function delete(string $id): void
    {
        $this->http->delete("credit-notes/{$id}");
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
     * Liste les lignes d'une facture encore creditables (apres avoirs anterieurs),
     * avec la quantite restante et le taux de TVA exact par ligne.
     *
     * Etape de decouverte AVANT un avoir partiel : choisir des `invoice_line_id`
     * dans `data.items[]` puis les passer a {@see create()}.
     *
     * @param string $invoiceId UUID de la facture
     * @return array{data: array{
     *     invoice_id: string,
     *     invoice_number: string,
     *     items: array<int, array{invoice_line_id: string, description: string, original_quantity: float, credited_quantity: float, remaining_quantity: float, unit_price: float, tax_rate: float, remaining_amount_ht: float}>,
     *     total_remaining: float,
     *     can_be_credited: bool
     * }}
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
