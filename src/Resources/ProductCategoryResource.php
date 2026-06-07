<?php

declare(strict_types=1);

namespace Scell\Sdk\Resources;

use Scell\Sdk\DTOs\PaginatedResult;
use Scell\Sdk\DTOs\ProductCategory;
use Scell\Sdk\Http\HttpClient;

/**
 * Resource pour les categories de produits du catalogue (ProductCategories).
 *
 * Les categories permettent de regrouper les produits/services
 * ({@see ProductResource}). Le registre est scope strictement par
 * (tenant, sub_tenant) — vous ne verrez que les categories de votre
 * propre scope.
 *
 * @since 2.37.0
 */
class ProductCategoryResource
{
    public function __construct(
        private readonly HttpClient $http,
    ) {}

    /**
     * Liste paginee des categories avec recherche.
     *
     * @param array{
     *     q?: string,
     *     per_page?: int,
     *     page?: int,
     * } $params
     * @return PaginatedResult<ProductCategory>
     */
    public function list(array $params = []): PaginatedResult
    {
        $response = $this->http->get('product-categories', $params);
        return PaginatedResult::fromArray($response, fn(array $data) => ProductCategory::fromArray($data));
    }

    /**
     * Recupere une categorie par son id.
     */
    public function get(string $id): ProductCategory
    {
        $response = $this->http->get("product-categories/{$id}");
        return ProductCategory::fromArray($response['data']);
    }

    /**
     * Cree une categorie.
     *
     * @param array{
     *     name: string,
     *     color?: string,
     *     description?: string,
     *     position?: int,
     *     metadata?: array,
     * } $data
     */
    public function create(array $data): ProductCategory
    {
        $response = $this->http->post('product-categories', $data);
        return ProductCategory::fromArray($response['data']);
    }

    /**
     * Met a jour une categorie (PATCH partiel).
     *
     * @param array<string, mixed> $data
     */
    public function update(string $id, array $data): ProductCategory
    {
        $response = $this->http->patch("product-categories/{$id}", $data);
        return ProductCategory::fromArray($response['data']);
    }

    /**
     * Remplace une categorie (PUT complet).
     *
     * @param array<string, mixed> $data
     */
    public function replace(string $id, array $data): ProductCategory
    {
        $response = $this->http->put("product-categories/{$id}", $data);
        return ProductCategory::fromArray($response['data']);
    }

    public function delete(string $id): void
    {
        $this->http->delete("product-categories/{$id}");
    }
}
