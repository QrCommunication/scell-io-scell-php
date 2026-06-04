<?php

declare(strict_types=1);

namespace Scell\Sdk\Resources;

use Scell\Sdk\DTOs\Address;
use Scell\Sdk\DTOs\PaginatedResult;
use Scell\Sdk\DTOs\RecurringInvoiceOccurrence;
use Scell\Sdk\DTOs\RecurringInvoiceProfile;
use Scell\Sdk\Http\HttpClient;

/**
 * Resource pour la facturation recurrente (abonnements).
 *
 * Un profil recurrent decrit un gabarit de facture (buyer, lignes, format)
 * et une cadence (recurrence). A chaque echeance, le backend genere une
 * occurrence -> une facture (brouillon ou auto-soumise selon
 * `emission_mode`).
 *
 *   $profile = $client->recurringInvoices()->create([
 *       'title' => 'Abonnement mensuel SaaS',
 *       'buyer_id' => $buyer->id,
 *       'lines' => [
 *           ['description' => 'Licence', 'quantity' => 1, 'unit_price' => 49.0, 'vat_rate' => 20.0],
 *       ],
 *       'recurrence' => ['interval_unit' => 'month', 'interval_count' => 1, 'day_of_month' => 1],
 *       'start_date' => '2026-07-01',
 *       'emission_mode' => 'auto_send',
 *   ]);
 *
 *   // Suspendre puis reprendre
 *   $client->recurringInvoices()->pause($profile->id);
 *   $client->recurringInvoices()->activate($profile->id);
 *
 *   // Forcer une emission immediate (hors cadence)
 *   $client->recurringInvoices()->runNow($profile->id);
 *
 *   // Suivre les echeances
 *   $occurrences = $client->recurringInvoices()->occurrences($profile->id);
 *
 * Comme pour le registre des buyers, le profil est scope par (tenant,
 * sub_tenant). Les factures emises restent immutables (ISCA) : modifier le
 * profil plus tard n'impacte pas les occurrences deja emises.
 */
class RecurringInvoiceResource
{
    public function __construct(
        private readonly HttpClient $http,
    ) {}

    /**
     * Liste paginee des profils de facturation recurrente.
     *
     * @param array{
     *     status?: string,
     *     sub_tenant_id?: string,
     *     per_page?: int,
     *     page?: int,
     * } $filters
     * @return PaginatedResult<RecurringInvoiceProfile>
     */
    public function list(array $filters = []): PaginatedResult
    {
        $response = $this->http->get('recurring-invoices', $filters);
        return PaginatedResult::fromArray(
            $response,
            fn(array $data) => RecurringInvoiceProfile::fromArray($data),
        );
    }

    /**
     * Recupere un profil par son id.
     */
    public function get(string $id): RecurringInvoiceProfile
    {
        $response = $this->http->get("recurring-invoices/{$id}");
        return RecurringInvoiceProfile::fromArray($response['data']);
    }

    /**
     * Cree un profil de facturation recurrente.
     *
     * @param array{
     *     title: string,
     *     lines: array<int, array{
     *         description: string,
     *         quantity: float|int,
     *         unit_price: float|int,
     *         vat_rate: float|int,
     *         unit?: string,
     *         discount?: float|int,
     *         category?: string,
     *         metadata?: array
     *     }>,
     *     recurrence: array{
     *         interval_unit: string,
     *         interval_count?: int,
     *         day_of_month?: int,
     *         day_of_week?: int
     *     },
     *     start_date: string,
     *     sub_tenant_id?: string,
     *     buyer_id?: string,
     *     buyer_name?: string,
     *     buyer_country?: string,
     *     buyer_is_individual?: bool,
     *     buyer_siret?: string,
     *     buyer_vat_number?: string,
     *     buyer_email?: string,
     *     buyer_address?: Address|array,
     *     buyer_shipping_address?: Address|array,
     *     currency?: string,
     *     output_format?: string,
     *     payment_terms?: string,
     *     end_mode?: string,
     *     end_date?: string,
     *     max_occurrences?: int,
     *     emission_mode?: string,
     *     notify_before_days?: int,
     *     metadata?: array,
     * } $data
     */
    public function create(array $data): RecurringInvoiceProfile
    {
        $payload = $this->normalizePayload($data);
        $response = $this->http->post('recurring-invoices', $payload);
        return RecurringInvoiceProfile::fromArray($response['data']);
    }

    /**
     * Met a jour un profil (PUT).
     *
     * @param array<string, mixed> $data
     */
    public function update(string $id, array $data): RecurringInvoiceProfile
    {
        $payload = $this->normalizePayload($data);
        $response = $this->http->put("recurring-invoices/{$id}", $payload);
        return RecurringInvoiceProfile::fromArray($response['data']);
    }

    public function delete(string $id): void
    {
        $this->http->delete("recurring-invoices/{$id}");
    }

    /**
     * Liste paginee des occurrences (echeances) d'un profil.
     *
     * @param array{
     *     status?: string,
     *     per_page?: int,
     *     page?: int,
     * } $filters
     * @return PaginatedResult<RecurringInvoiceOccurrence>
     */
    public function occurrences(string $id, array $filters = []): PaginatedResult
    {
        $response = $this->http->get("recurring-invoices/{$id}/occurrences", $filters);
        return PaginatedResult::fromArray(
            $response,
            fn(array $data) => RecurringInvoiceOccurrence::fromArray($data),
        );
    }

    /**
     * Met le profil en pause (aucune emission tant que non reactive).
     */
    public function pause(string $id): RecurringInvoiceProfile
    {
        $response = $this->http->post("recurring-invoices/{$id}/pause");
        return RecurringInvoiceProfile::fromArray($response['data']);
    }

    /**
     * Reactive un profil en pause.
     */
    public function activate(string $id): RecurringInvoiceProfile
    {
        $response = $this->http->post("recurring-invoices/{$id}/activate");
        return RecurringInvoiceProfile::fromArray($response['data']);
    }

    /**
     * Annule definitivement le profil (terminal).
     */
    public function cancel(string $id): RecurringInvoiceProfile
    {
        $response = $this->http->post("recurring-invoices/{$id}/cancel");
        return RecurringInvoiceProfile::fromArray($response['data']);
    }

    /**
     * Declenche immediatement une emission hors cadence.
     *
     * Traite de maniere asynchrone (202) : la facture est generee par le
     * worker. Suivre le resultat via {@see self::occurrences()}.
     *
     * @return array{message?: string, ...} Reponse brute du backend (202).
     */
    public function runNow(string $id): array
    {
        return $this->http->post("recurring-invoices/{$id}/run-now");
    }

    /**
     * Normalise le payload : convertit les Address DTO en tableaux.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizePayload(array $data): array
    {
        $payload = $data;
        foreach (['buyer_address', 'buyer_shipping_address'] as $key) {
            if (isset($payload[$key]) && $payload[$key] instanceof Address) {
                $payload[$key] = $payload[$key]->toArray();
            }
        }
        return $payload;
    }
}
