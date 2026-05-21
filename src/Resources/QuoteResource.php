<?php

declare(strict_types=1);

namespace Scell\Sdk\Resources;

use DateTimeInterface;
use Scell\Sdk\Builders\QuoteBuilder;
use Scell\Sdk\DTOs\Invoice;
use Scell\Sdk\DTOs\PaginatedResult;
use Scell\Sdk\DTOs\Quote;
use Scell\Sdk\DTOs\QuoteAuditEntry;
use Scell\Sdk\Http\HttpClient;

/**
 * Resource pour les devis (quotes).
 *
 * Permet de creer, envoyer, signer et convertir des devis en factures.
 *
 * @example
 * ```php
 * $api = ScellApiClient::withApiKey('sk_live_...');
 *
 * // Creer un devis
 * $quote = $api->quotes()->builder()
 *     ->buyer('12345678901234', 'Client SA', new Address('1 rue Test', '75001', 'Paris'))
 *     ->line('Prestation conseil', 1, 2500.00, 20.0)
 *     ->validUntil(new DateTime('+30 days'))
 *     ->signatureRequired()
 *     ->create();
 *
 * // Envoyer par email
 * $api->quotes()->send($quote->id, ['email' => 'client@example.com']);
 *
 * // Convertir en facture d'acompte (30%)
 * $depositInvoice = $api->quotes()->convertToDeposit($quote->id, ['percent' => 30]);
 * ```
 */
class QuoteResource
{
    private ?QuotePaymentScheduleResource $paymentSchedule = null;

    public function __construct(
        private readonly HttpClient $http
    ) {}

    /**
     * Resource pour l'echeancier de paiement du devis.
     *
     * Permet de definir, modifier, tracker et convertir les echeances.
     *
     * @example
     * ```php
     * $api->quotes()->paymentSchedule()->set($quoteId, [
     *     ['amount_type' => 'percent', 'amount_value' => 30, 'due_date' => '2026-06-15'],
     *     ['amount_type' => 'percent', 'amount_value' => 70, 'milestone_label' => 'Livraison'],
     * ]);
     * $summary = $api->quotes()->paymentSchedule()->summary($quoteId);
     * ```
     */
    public function paymentSchedule(): QuotePaymentScheduleResource
    {
        return $this->paymentSchedule ??= new QuotePaymentScheduleResource($this->http);
    }

    /**
     * Cree un nouveau devis.
     *
     * @param array{
     *     buyer_name: string,
     *     buyer_address: array,
     *     lines: array[],
     *     buyer_siret?: string,
     *     buyer_id?: string,
     *     buyer_is_individual?: bool,
     *     valid_until?: string,
     *     signature_required?: bool,
     *     notes?: string,
     *     payment_terms?: string,
     *     metadata?: array,
     *     sub_tenant_id?: string,
     *     company_id?: string,
     *     external_id?: string,
     *     currency?: string,
     *     deposit_schedule?: array
     * } $data
     */
    public function create(array $data): Quote
    {
        $response = $this->http->post('quotes', $data);
        return Quote::fromArray($response['data']);
    }

    /**
     * Liste les devis avec filtrage optionnel.
     *
     * @param array{
     *     status?: string,
     *     sub_tenant_id?: string,
     *     company_id?: string,
     *     buyer_id?: string,
     *     from?: DateTimeInterface|string,
     *     to?: DateTimeInterface|string,
     *     per_page?: int,
     *     page?: int,
     *     sort?: string,
     *     direction?: string
     * } $params
     * @return PaginatedResult<Quote>
     */
    public function list(array $params = []): PaginatedResult
    {
        $query = $this->normalizeFilters($params);
        $response = $this->http->get('quotes', $query);
        return PaginatedResult::fromArray($response, fn(array $d) => Quote::fromArray($d));
    }

    /**
     * Recupere un devis par son ID.
     */
    public function get(string $id): Quote
    {
        $response = $this->http->get("quotes/{$id}");
        return Quote::fromArray($response['data']);
    }

    /**
     * Met a jour un devis en statut brouillon.
     *
     * @param array{
     *     buyer_name?: string,
     *     buyer_address?: array,
     *     lines?: array[],
     *     valid_until?: string,
     *     notes?: string,
     *     payment_terms?: string,
     *     metadata?: array,
     *     signature_required?: bool
     * } $data
     */
    public function update(string $id, array $data): Quote
    {
        $response = $this->http->patch("quotes/{$id}", $data);
        return Quote::fromArray($response['data']);
    }

    /**
     * Supprime un devis en statut brouillon.
     */
    public function delete(string $id): void
    {
        $this->http->delete("quotes/{$id}");
    }

    /**
     * Envoie le devis par email au buyer.
     *
     * @param array{
     *     email?: string,
     *     subject?: string,
     *     message?: string,
     *     cc?: string[]
     * } $params
     */
    public function send(string $id, array $params = []): Quote
    {
        $response = $this->http->post("quotes/{$id}/send", $params);
        return Quote::fromArray($response['data']);
    }

    /**
     * Annule un devis.
     *
     * @param string $reason Motif d'annulation (optionnel)
     */
    public function cancel(string $id, string $reason = ''): Quote
    {
        $payload = $reason !== '' ? ['reason' => $reason] : [];
        $response = $this->http->post("quotes/{$id}/cancel", $payload);
        return Quote::fromArray($response['data']);
    }

    /**
     * Duplique un devis existant (cree un nouveau brouillon identique).
     */
    public function duplicate(string $id): Quote
    {
        $response = $this->http->post("quotes/{$id}/duplicate");
        return Quote::fromArray($response['data']);
    }

    /**
     * Convertit un devis accepte en facture d'acompte.
     *
     * @param array{
     *     percent?: float,
     *     amount?: float,
     *     label?: string,
     *     issue_date?: string,
     *     due_date?: string,
     *     output_format?: string
     * } $params
     */
    public function convertToDeposit(string $id, array $params = []): Invoice
    {
        $response = $this->http->post("quotes/{$id}/convert-to-deposit", $params);
        return Invoice::fromArray($response['data']);
    }

    /**
     * Convertit un devis accepte en facture de solde (balance).
     *
     * Deduit automatiquement les acomptes deja factures.
     *
     * @param DateTimeInterface|string|null $dueDate Date d'echeance de la facture de solde
     */
    public function convertToBalance(string $id, DateTimeInterface|string|null $dueDate = null): Invoice
    {
        $payload = [];
        if ($dueDate !== null) {
            $payload['due_date'] = $dueDate instanceof DateTimeInterface
                ? $dueDate->format('Y-m-d')
                : $dueDate;
        }
        $response = $this->http->post("quotes/{$id}/convert-to-balance", $payload);
        return Invoice::fromArray($response['data']);
    }

    /**
     * Recupere le journal d'audit du devis.
     *
     * @return QuoteAuditEntry[]
     */
    public function auditLog(string $id): array
    {
        $response = $this->http->get("quotes/{$id}/audit-log");
        return array_map(
            fn(array $entry) => QuoteAuditEntry::fromArray($entry),
            $response['data'] ?? []
        );
    }

    /**
     * Regenere le lien public de consultation/signature du devis.
     *
     * @param int $ttlDays Duree de validite en jours (defaut : 30)
     * @return array{public_url: string, expires_at: string}
     */
    public function regeneratePublicLink(string $id, int $ttlDays = 30): array
    {
        return $this->http->post("quotes/{$id}/regenerate-public-link", [
            'ttl_days' => $ttlDays,
        ]);
    }

    /**
     * Revoque le lien public du devis (le buyer ne peut plus le consulter).
     */
    public function revokePublicLink(string $id): void
    {
        $this->http->post("quotes/{$id}/revoke-public-link");
    }

    /**
     * Telecharge le PDF du devis (contenu binaire).
     *
     * @return string Contenu binaire du PDF
     */
    public function pdf(string $id): string
    {
        return $this->http->getRaw("quotes/{$id}/pdf");
    }

    /**
     * Genere un apercu PDF a partir des donnees fournies (sans sauvegarder).
     *
     * @param array $data Donnees du devis (meme format que create())
     * @return string Contenu binaire du PDF d'apercu
     */
    public function preview(array $data): string
    {
        return $this->http->postRaw('quotes/preview', $data);
    }

    /**
     * Retourne un builder fluent pour creer un devis.
     */
    public function builder(): QuoteBuilder
    {
        return new QuoteBuilder($this);
    }

    /**
     * Normalise les filtres de liste (dates, enums).
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
