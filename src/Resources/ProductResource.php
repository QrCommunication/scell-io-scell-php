<?php

declare(strict_types=1);

namespace Scell\Sdk\Resources;

use Scell\Sdk\DTOs\PaginatedResult;
use Scell\Sdk\DTOs\Product;
use Scell\Sdk\Http\HttpClient;

/**
 * Resource pour le catalogue de produits/services (Products).
 *
 * Le catalogue est scope strictement par (tenant, sub_tenant) — vous ne
 * verrez que les produits de votre propre scope. Reutilisez les produits
 * pour pre-remplir les lignes de facture/devis sans resaisir le libelle,
 * le prix HT et le taux de TVA :
 *
 *   $product = $client->products()->create([
 *       'name' => 'Prestation de conseil',
 *       'unit_price_ht' => 800.00,
 *       'default_tax_rate' => 20.0,
 *   ]);
 *
 *   $client->invoices()->create([
 *       'lines' => [
 *           ['product_id' => $product->id, 'quantity' => 2],
 *       ],
 *       // ... reste de la facture
 *   ]);
 *
 * Modifier un produit plus tard n'impacte pas les factures deja emises
 * (snapshot immutable cote facture, ISCA fiscal compliance).
 *
 * @since 2.37.0
 */
class ProductResource
{
    public function __construct(
        private readonly HttpClient $http,
    ) {}

    /**
     * Liste paginee des produits avec recherche et filtres.
     *
     * @param array{
     *     q?: string,
     *     revenue_category?: string,
     *     product_category_id?: string,
     *     is_active?: bool,
     *     per_page?: int,
     *     page?: int,
     * } $params
     * @return PaginatedResult<Product>
     */
    public function list(array $params = []): PaginatedResult
    {
        $response = $this->http->get('products', $params);
        return PaginatedResult::fromArray($response, fn(array $data) => Product::fromArray($data));
    }

    /**
     * Recupere un produit par son id.
     */
    public function get(string $id): Product
    {
        $response = $this->http->get("products/{$id}");
        return Product::fromArray($response['data']);
    }

    /**
     * Cree un produit.
     *
     * @param array{
     *     name: string,
     *     unit_price_ht: float|int,
     *     product_category_id?: string,
     *     description?: string,
     *     sku?: string,
     *     revenue_category?: string,
     *     unit?: string,
     *     default_tax_rate?: float|int,
     *     default_discount_rate?: float|int,
     *     currency?: string,
     *     is_active?: bool,
     *     metadata?: array,
     *     notes?: string,
     * } $data
     */
    public function create(array $data): Product
    {
        $response = $this->http->post('products', $data);
        return Product::fromArray($response['data']);
    }

    /**
     * Met a jour un produit (PATCH partiel).
     *
     * @param array<string, mixed> $data
     */
    public function update(string $id, array $data): Product
    {
        $response = $this->http->patch("products/{$id}", $data);
        return Product::fromArray($response['data']);
    }

    /**
     * Remplace un produit (PUT complet).
     *
     * @param array<string, mixed> $data
     */
    public function replace(string $id, array $data): Product
    {
        $response = $this->http->put("products/{$id}", $data);
        return Product::fromArray($response['data']);
    }

    public function delete(string $id): void
    {
        $this->http->delete("products/{$id}");
    }
}
