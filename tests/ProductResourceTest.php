<?php

declare(strict_types=1);

namespace Scell\Sdk\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Scell\Sdk\DTOs\PaginatedResult;
use Scell\Sdk\DTOs\Product;
use Scell\Sdk\DTOs\ProductCategory;
use Scell\Sdk\Http\HttpClient;
use Scell\Sdk\Resources\ProductResource;

/**
 * Tests pour ProductResource (SDK v2.37.0).
 *
 * Miroir de BuyerResourceTest : assert URL + payload + mapping DTO du
 * catalogue de produits/services scope (tenant, sub_tenant).
 */
class ProductResourceTest extends TestCase
{
    /**
     * @param Response[] $responses
     * @param array<int, \Psr\Http\Message\RequestInterface> $captured
     */
    private function buildHttp(array $responses, array &$captured): HttpClient
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(function (callable $handler) use (&$captured) {
            return function ($request, array $options) use ($handler, &$captured) {
                $captured[] = $request;
                return $handler($request, $options);
            };
        });

        $http = new HttpClient('https://api.scell.io/api/v1');
        $http->withApiKey('sk_test_xxx');

        $clientProp = new ReflectionProperty(HttpClient::class, 'client');
        $clientProp->setAccessible(true);
        $clientProp->setValue($http, new Client([
            'handler'     => $stack,
            'http_errors' => false,
        ]));

        return $http;
    }

    /**
     * @return array<string, mixed>
     */
    private function productPayload(string $id = 'product-uuid-1'): array
    {
        return [
            'id'                    => $id,
            'tenant_id'             => 'tenant-uuid',
            'sub_tenant_id'         => null,
            'product_category_id'   => 'cat-uuid-1',
            'name'                  => 'Prestation de conseil',
            'description'           => 'Accompagnement strategique',
            'sku'                   => 'CONSEIL-01',
            'revenue_category'      => 'service',
            'revenue_category_label' => 'Prestation de services',
            'unit'                  => 'HUR',
            'unit_price_ht'         => 800.0,
            'default_tax_rate'      => 20.0,
            'default_discount_rate' => 5.0,
            'currency'              => 'EUR',
            'is_active'             => true,
            'product_category'      => [
                'id'        => 'cat-uuid-1',
                'tenant_id' => 'tenant-uuid',
                'name'      => 'Conseil',
                'color'     => '#0066FF',
                'position'  => 1,
            ],
            'metadata'              => ['ref' => 'P-001'],
            'notes'                 => 'Tarif horaire negociable',
            'created_at'            => '2026-06-07T10:00:00+00:00',
            'updated_at'            => '2026-06-07T10:00:00+00:00',
        ];
    }

    // -------------------------------------------------------------------------
    // list()
    // -------------------------------------------------------------------------

    #[Test]
    public function list_calls_products_endpoint_with_filters_and_returns_paginated_dtos(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => [
                    $this->productPayload('product-1'),
                    $this->productPayload('product-2'),
                ],
                'meta' => [
                    'current_page' => 1,
                    'last_page'    => 1,
                    'per_page'     => 25,
                    'total'        => 2,
                ],
            ]))],
            $captured,
        );

        $result = (new ProductResource($http))->list([
            'q'                   => 'Conseil',
            'revenue_category'    => 'service',
            'product_category_id' => 'cat-uuid-1',
            'is_active'           => true,
            'per_page'            => 25,
        ]);

        $this->assertInstanceOf(PaginatedResult::class, $result);
        $this->assertCount(2, $result->data);
        $this->assertContainsOnlyInstancesOf(Product::class, $result->data);
        $this->assertSame('product-1', $result->data[0]->id);
        $this->assertSame(2, $result->total);

        $this->assertCount(1, $captured);
        $this->assertSame('GET', $captured[0]->getMethod());
        $uri = (string) $captured[0]->getUri();
        $this->assertStringContainsString('/products', $uri);
        $this->assertStringContainsString('q=Conseil', $uri);
        $this->assertStringContainsString('revenue_category=service', $uri);
        $this->assertStringContainsString('product_category_id=cat-uuid-1', $uri);
        $this->assertStringContainsString('per_page=25', $uri);
    }

    // -------------------------------------------------------------------------
    // get()
    // -------------------------------------------------------------------------

    #[Test]
    public function get_calls_products_id_endpoint_and_returns_dto(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => $this->productPayload('product-42'),
            ]))],
            $captured,
        );

        $product = (new ProductResource($http))->get('product-42');

        $this->assertInstanceOf(Product::class, $product);
        $this->assertSame('product-42', $product->id);
        $this->assertSame('Prestation de conseil', $product->name);

        $this->assertSame('GET', $captured[0]->getMethod());
        $this->assertStringContainsString('/products/product-42', (string) $captured[0]->getUri());
    }

    // -------------------------------------------------------------------------
    // create()
    // -------------------------------------------------------------------------

    #[Test]
    public function create_posts_products_and_returns_dto(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(201, ['Content-Type' => 'application/json'], json_encode([
                'data' => $this->productPayload('product-new'),
            ]))],
            $captured,
        );

        $product = (new ProductResource($http))->create([
            'name'             => 'Prestation de conseil',
            'unit_price_ht'    => 800.0,
            'default_tax_rate' => 20.0,
        ]);

        $this->assertSame('product-new', $product->id);

        $this->assertSame('POST', $captured[0]->getMethod());
        $this->assertStringContainsString('/products', (string) $captured[0]->getUri());

        $body = json_decode((string) $captured[0]->getBody(), true);
        $this->assertSame('Prestation de conseil', $body['name']);
        $this->assertEquals(800.0, $body['unit_price_ht']);
        $this->assertEquals(20.0, $body['default_tax_rate']);
    }

    // -------------------------------------------------------------------------
    // update() — PATCH
    // -------------------------------------------------------------------------

    #[Test]
    public function update_patches_product_and_returns_dto(): void
    {
        $captured = [];
        $payload = $this->productPayload('product-7');
        $payload['name'] = 'Nouveau libelle';
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => $payload,
            ]))],
            $captured,
        );

        $product = (new ProductResource($http))->update('product-7', [
            'name' => 'Nouveau libelle',
        ]);

        $this->assertSame('Nouveau libelle', $product->name);

        $this->assertSame('PATCH', $captured[0]->getMethod());
        $this->assertStringContainsString('/products/product-7', (string) $captured[0]->getUri());

        $body = json_decode((string) $captured[0]->getBody(), true);
        $this->assertSame('Nouveau libelle', $body['name']);
    }

    // -------------------------------------------------------------------------
    // replace() — PUT
    // -------------------------------------------------------------------------

    #[Test]
    public function replace_puts_product_and_returns_dto(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => $this->productPayload('product-9'),
            ]))],
            $captured,
        );

        (new ProductResource($http))->replace('product-9', [
            'name'          => 'Prestation de conseil',
            'unit_price_ht' => 900.0,
        ]);

        $this->assertSame('PUT', $captured[0]->getMethod());
        $this->assertStringContainsString('/products/product-9', (string) $captured[0]->getUri());

        $body = json_decode((string) $captured[0]->getBody(), true);
        $this->assertEquals(900.0, $body['unit_price_ht']);
    }

    // -------------------------------------------------------------------------
    // delete()
    // -------------------------------------------------------------------------

    #[Test]
    public function delete_calls_products_id_endpoint(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(204, [], '')],
            $captured,
        );

        (new ProductResource($http))->delete('product-x');

        $this->assertSame('DELETE', $captured[0]->getMethod());
        $this->assertStringContainsString('/products/product-x', (string) $captured[0]->getUri());
    }

    // -------------------------------------------------------------------------
    // DTO mapping
    // -------------------------------------------------------------------------

    #[Test]
    public function product_dto_maps_all_fields_from_api_payload(): void
    {
        $product = Product::fromArray($this->productPayload('product-dto'));

        $this->assertSame('product-dto', $product->id);
        $this->assertSame('tenant-uuid', $product->tenantId);
        $this->assertNull($product->subTenantId);
        $this->assertSame('cat-uuid-1', $product->productCategoryId);
        $this->assertSame('Prestation de conseil', $product->name);
        $this->assertSame('Accompagnement strategique', $product->description);
        $this->assertSame('CONSEIL-01', $product->sku);
        $this->assertSame('service', $product->revenueCategory);
        $this->assertSame('Prestation de services', $product->revenueCategoryLabel);
        $this->assertSame('HUR', $product->unit);
        $this->assertSame(800.0, $product->unitPriceHt);
        $this->assertSame(20.0, $product->defaultTaxRate);
        $this->assertSame(5.0, $product->defaultDiscountRate);
        $this->assertSame('EUR', $product->currency);
        $this->assertTrue($product->isActive);
        $this->assertInstanceOf(ProductCategory::class, $product->productCategory);
        $this->assertSame('Conseil', $product->productCategory->name);
        $this->assertSame(['ref' => 'P-001'], $product->metadata);
        $this->assertSame('Tarif horaire negociable', $product->notes);
        $this->assertNotNull($product->createdAt);
        $this->assertNotNull($product->updatedAt);
    }

    #[Test]
    public function product_dto_derives_defaults_for_minimal_payload(): void
    {
        $product = Product::fromArray([
            'id'            => 'product-min',
            'tenant_id'     => 'tenant-uuid',
            'name'          => 'Article simple',
            'unit_price_ht' => 12.5,
        ]);

        $this->assertSame('product-min', $product->id);
        $this->assertSame(12.5, $product->unitPriceHt);
        $this->assertSame('C62', $product->unit);
        $this->assertSame('EUR', $product->currency);
        $this->assertSame(0.0, $product->defaultTaxRate);
        $this->assertTrue($product->isActive);
        $this->assertNull($product->productCategoryId);
        $this->assertNull($product->revenueCategory);
        $this->assertNull($product->defaultDiscountRate);
        $this->assertNull($product->productCategory);
        $this->assertNull($product->metadata);
        $this->assertNull($product->createdAt);
    }
}
