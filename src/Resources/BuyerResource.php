<?php

declare(strict_types=1);

namespace Scell\Sdk\Resources;

use Scell\Sdk\DTOs\Address;
use Scell\Sdk\DTOs\Buyer;
use Scell\Sdk\DTOs\PaginatedResult;
use Scell\Sdk\Http\HttpClient;

/**
 * Resource pour le registre des acheteurs (Buyers).
 *
 * Le registre est scope strictement par (tenant, sub_tenant) — vous ne
 * verrez que les acheteurs de votre propre scope. Reutilisez les buyers
 * pour emettre des factures sans resaisir l'identite et l'adresse :
 *
 *   $buyer = $client->buyers()->create([...]);
 *   $client->invoices()->create([
 *       'buyer_id' => $buyer->id,
 *       // ... reste de la facture
 *   ]);
 *
 * Modifier un buyer plus tard n'impacte pas les factures deja emises
 * (snapshot immutable cote facture, ISCA fiscal compliance).
 */
class BuyerResource
{
    public function __construct(
        private readonly HttpClient $http,
    ) {}

    /**
     * Liste paginee des acheteurs avec recherche et filtres.
     *
     * @param array{
     *     q?: string,
     *     is_individual?: bool,
     *     per_page?: int,
     *     page?: int,
     * } $params
     * @return PaginatedResult<Buyer>
     */
    public function list(array $params = []): PaginatedResult
    {
        $response = $this->http->get('buyers', $params);
        return PaginatedResult::fromArray($response, fn(array $data) => Buyer::fromArray($data));
    }

    /**
     * Recupere un acheteur par son id.
     */
    public function get(string $id): Buyer
    {
        $response = $this->http->get("buyers/{$id}");
        return Buyer::fromArray($response['data']);
    }

    /**
     * Cree un acheteur.
     *
     * @param array{
     *     name: string,
     *     country: string,
     *     billing_address: Address|array,
     *     is_individual?: bool,
     *     siret?: string,
     *     vat_number?: string,
     *     legal_id?: string,
     *     legal_id_scheme?: string,
     *     email?: string,
     *     phone?: string,
     *     shipping_address?: Address|array,
     *     metadata?: array,
     *     notes?: string,
     * } $data
     */
    public function create(array $data): Buyer
    {
        $payload = $this->normalizePayload($data);
        $response = $this->http->post('buyers', $payload);
        return Buyer::fromArray($response['data']);
    }

    /**
     * Met a jour un acheteur (PATCH partiel).
     *
     * @param array<string, mixed> $data
     */
    public function update(string $id, array $data): Buyer
    {
        $payload = $this->normalizePayload($data);
        $response = $this->http->patch("buyers/{$id}", $payload);
        return Buyer::fromArray($response['data']);
    }

    public function delete(string $id): void
    {
        $this->http->delete("buyers/{$id}");
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizePayload(array $data): array
    {
        $payload = $data;
        foreach (['billing_address', 'shipping_address'] as $key) {
            if (isset($payload[$key]) && $payload[$key] instanceof Address) {
                $payload[$key] = $payload[$key]->toArray();
            }
        }
        return $payload;
    }
}
