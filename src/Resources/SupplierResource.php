<?php

declare(strict_types=1);

namespace Scell\Sdk\Resources;

use Scell\Sdk\DTOs\PaginatedResult;
use Scell\Sdk\DTOs\Supplier;
use Scell\Sdk\Http\HttpClient;

/**
 * Resource pour le registre des fournisseurs (Suppliers).
 *
 * Les fournisseurs sont dérivés automatiquement des factures reçues côté
 * serveur — la facture est la source de vérité pour l'identité
 * (name, siret, vat_number, legal_id, country, billing_address, is_individual).
 *
 * Seuls les champs d'enrichissement (email, phone, notes, metadata) sont
 * modifiables via {@see update()}.
 *
 * @since 2.26.0
 * @breaking 3.0.0 create() et delete() supprimés (API 405 — endpoints fermés côté serveur)
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
     * Met a jour les champs d'enrichissement d'un fournisseur (PATCH partiel).
     *
     * Seuls email, phone, notes et metadata sont acceptés par l'API.
     * Les champs d'identité (name, siret, vat_number, legal_id, legal_id_scheme,
     * country, billing_address, is_individual) sont ignorés côté serveur car
     * ils sont dérivés des factures reçues.
     *
     * @param array{
     *     email?: string|null,
     *     phone?: string|null,
     *     notes?: string|null,
     *     metadata?: array|null,
     * } $data
     */
    public function update(string $id, array $data): Supplier
    {
        $payload = array_intersect_key($data, array_flip(['email', 'phone', 'notes', 'metadata']));
        $response = $this->http->patch("suppliers/{$id}", $payload);
        return Supplier::fromArray($response['data']);
    }
}
