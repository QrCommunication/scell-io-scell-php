<?php

declare(strict_types=1);

namespace Scell\Sdk\Resources;

use Scell\Sdk\DTOs\Address;
use Scell\Sdk\DTOs\PaginatedResult;
use Scell\Sdk\DTOs\Supplier;
use Scell\Sdk\Http\HttpClient;

/**
 * Resource pour le registre des fournisseurs (Suppliers).
 *
 * Miroir de {@see BuyerResource}, cote emetteur. Le registre est scope
 * strictement par (tenant, sub_tenant) — vous ne verrez que les
 * fournisseurs de votre propre scope. Reutilisez-les pour memoriser
 * l'identite et l'adresse de facturation de vos partenaires :
 *
 *   $supplier = $client->suppliers()->create([...]);
 *   $client->suppliers()->get($supplier->id);
 *
 * Contrairement aux acheteurs, un fournisseur n'a pas d'adresse de
 * livraison ni d'email de facturation distinct.
 */
class SupplierResource
{
    public function __construct(
        private readonly HttpClient $http,
    ) {}

    /**
     * Liste paginee des fournisseurs avec recherche et filtres.
     *
     * @param array{
     *     q?: string,
     *     is_individual?: bool,
     *     per_page?: int,
     *     page?: int,
     * } $params
     * @return PaginatedResult<Supplier>
     */
    public function list(array $params = []): PaginatedResult
    {
        $response = $this->http->get('suppliers', $params);
        return PaginatedResult::fromArray($response, fn(array $data) => Supplier::fromArray($data));
    }

    /**
     * Recupere un fournisseur par son id.
     */
    public function get(string $id): Supplier
    {
        $response = $this->http->get("suppliers/{$id}");
        return Supplier::fromArray($response['data']);
    }

    /**
     * Cree un fournisseur.
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
     *     metadata?: array,
     *     notes?: string,
     * } $data
     */
    public function create(array $data): Supplier
    {
        $payload = $this->normalizePayload($data);
        $response = $this->http->post('suppliers', $payload);
        return Supplier::fromArray($response['data']);
    }

    /**
     * Met a jour un fournisseur (PATCH partiel).
     *
     * @param array<string, mixed> $data
     */
    public function update(string $id, array $data): Supplier
    {
        $payload = $this->normalizePayload($data);
        $response = $this->http->patch("suppliers/{$id}", $payload);
        return Supplier::fromArray($response['data']);
    }

    public function delete(string $id): void
    {
        $this->http->delete("suppliers/{$id}");
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizePayload(array $data): array
    {
        $payload = $data;
        if (isset($payload['billing_address']) && $payload['billing_address'] instanceof Address) {
            $payload['billing_address'] = $payload['billing_address']->toArray();
        }
        return $payload;
    }
}
