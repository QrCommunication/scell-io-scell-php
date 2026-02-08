<?php

declare(strict_types=1);

namespace Scell\Sdk\Resources;

use Scell\Sdk\DTOs\PaginatedResult;
use Scell\Sdk\DTOs\SubTenant;
use Scell\Sdk\Http\HttpClient;

/**
 * Resource pour les sub-tenants (clients finaux).
 *
 * Permet de gerer les clients finaux d'un tenant partenaire.
 *
 * @example
 * ```php
 * $resource = new SubTenantResource($httpClient);
 *
 * // Lister les sub-tenants
 * $subTenants = $resource->list(['per_page' => 50]);
 *
 * // Creer un sub-tenant
 * $subTenant = $resource->create([
 *     'external_id' => 'CLIENT-001',
 *     'name' => 'Mon Client',
 *     'siret' => '12345678901234',
 * ]);
 *
 * // Rechercher par ID externe
 * $subTenant = $resource->findByExternalId('CLIENT-001');
 * ```
 */
class SubTenantResource
{
    public function __construct(
        private readonly HttpClient $http
    ) {}

    /**
     * Liste les sub-tenants avec pagination et filtres.
     *
     * @param array{
     *     search?: string,
     *     status?: string,
     *     per_page?: int,
     *     page?: int,
     *     sort?: string,
     *     order?: string
     * } $filters Filtres optionnels
     * @return PaginatedResult<SubTenant>
     *
     * @example
     * ```php
     * // Liste paginee
     * $subTenants = $resource->list(['per_page' => 25, 'page' => 1]);
     *
     * // Avec recherche
     * $subTenants = $resource->list(['search' => 'SARL']);
     * ```
     */
    public function list(array $filters = []): PaginatedResult
    {
        $response = $this->http->get('tenant/sub-tenants', $filters);

        return PaginatedResult::fromArray($response, fn(array $data) => SubTenant::fromArray($data));
    }

    /**
     * Cree un nouveau sub-tenant.
     *
     * @param array{
     *     external_id?: string,
     *     name: string,
     *     siret: string,
     *     siren?: string,
     *     vat_number?: string,
     *     email?: string,
     *     phone?: string,
     *     address_line1?: string,
     *     address_line2?: string,
     *     postal_code?: string,
     *     city?: string,
     *     country?: string,
     *     metadata?: array
     * } $data Donnees du sub-tenant
     *
     * @example
     * ```php
     * $subTenant = $resource->create([
     *     'external_id' => 'CLIENT-001',
     *     'name' => 'Mon Client SARL',
     *     'siret' => '12345678901234',
     *     'email' => 'contact@client.fr',
     *     'address_line1' => '1 rue de la Paix',
     *     'postal_code' => '75001',
     *     'city' => 'Paris',
     * ]);
     * ```
     */
    public function create(array $data): SubTenant
    {
        $response = $this->http->post('tenant/sub-tenants', $data);

        return SubTenant::fromArray($response['data']);
    }

    /**
     * Recupere un sub-tenant par son ID.
     *
     * @param string $id UUID du sub-tenant
     *
     * @example
     * ```php
     * $subTenant = $resource->get('550e8400-e29b-41d4-a716-446655440000');
     * ```
     */
    public function get(string $id): SubTenant
    {
        $response = $this->http->get("tenant/sub-tenants/{$id}");

        return SubTenant::fromArray($response['data']);
    }

    /**
     * Met a jour un sub-tenant.
     *
     * @param string $id UUID du sub-tenant
     * @param array{
     *     external_id?: string,
     *     name?: string,
     *     siret?: string,
     *     siren?: string,
     *     vat_number?: string,
     *     email?: string,
     *     phone?: string,
     *     address_line1?: string,
     *     address_line2?: string,
     *     postal_code?: string,
     *     city?: string,
     *     country?: string,
     *     metadata?: array
     * } $data Donnees a mettre a jour
     *
     * @example
     * ```php
     * $subTenant = $resource->update('550e8400-e29b-41d4-a716-446655440000', [
     *     'email' => 'nouveau@email.fr',
     *     'phone' => '+33612345678',
     * ]);
     * ```
     */
    public function update(string $id, array $data): SubTenant
    {
        $response = $this->http->put("tenant/sub-tenants/{$id}", $data);

        return SubTenant::fromArray($response['data']);
    }

    /**
     * Supprime un sub-tenant.
     *
     * Attention: Le sub-tenant ne doit pas avoir de factures ou avoirs actifs.
     *
     * @param string $id UUID du sub-tenant
     *
     * @example
     * ```php
     * $resource->delete('550e8400-e29b-41d4-a716-446655440000');
     * ```
     */
    public function delete(string $id): void
    {
        $this->http->delete("tenant/sub-tenants/{$id}");
    }

    /**
     * Recherche un sub-tenant par son ID externe.
     *
     * @param string $externalId ID externe (defini lors de la creation)
     *
     * @example
     * ```php
     * $subTenant = $resource->findByExternalId('CLIENT-001');
     * ```
     */
    public function findByExternalId(string $externalId): SubTenant
    {
        $response = $this->http->get("tenant/sub-tenants/by-external-id/{$externalId}");

        return SubTenant::fromArray($response['data']);
    }
}
