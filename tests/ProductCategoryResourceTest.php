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
use Scell\Sdk\DTOs\ProductCategory;
use Scell\Sdk\Http\HttpClient;
use Scell\Sdk\Resources\ProductCategoryResource;

/**
 * Tests pour ProductCategoryResource (SDK v2.37.0).
 *
 * Miroir de BuyerResourceTest : assert URL + payload + mapping DTO des
 * categories du catalogue scope (tenant, sub_tenant).
 */
class ProductCategoryResourceTest extends TestCase
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
    private function categoryPayload(string $id = 'cat-uuid-1'): array
    {
        return [
            'id'             => $id,
            'tenant_id'      => 'tenant-uuid',
            'sub_tenant_id'  => null,
            'name'           => 'Conseil',
            'color'          => '#0066FF',
            'description'    => 'Prestations de conseil',
            'position'       => 1,
            'products_count' => 4,
            'metadata'       => ['ref' => 'C-001'],
            'created_at'     => '2026-06-07T10:00:00+00:00',
            'updated_at'     => '2026-06-07T10:00:00+00:00',
        ];
    }

    // -------------------------------------------------------------------------
    // list()
    // -------------------------------------------------------------------------

    #[Test]
    public function list_calls_product_categories_endpoint_with_filters_and_returns_paginated_dtos(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => [
                    $this->categoryPayload('cat-1'),
                    $this->categoryPayload('cat-2'),
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

        $result = (new ProductCategoryResource($http))->list([
            'q'        => 'Conseil',
            'per_page' => 25,
        ]);

        $this->assertInstanceOf(PaginatedResult::class, $result);
        $this->assertCount(2, $result->data);
        $this->assertContainsOnlyInstancesOf(ProductCategory::class, $result->data);
        $this->assertSame('cat-1', $result->data[0]->id);
        $this->assertSame(2, $result->total);

        $this->assertCount(1, $captured);
        $this->assertSame('GET', $captured[0]->getMethod());
        $uri = (string) $captured[0]->getUri();
        $this->assertStringContainsString('/product-categories', $uri);
        $this->assertStringContainsString('q=Conseil', $uri);
        $this->assertStringContainsString('per_page=25', $uri);
    }

    // -------------------------------------------------------------------------
    // get()
    // -------------------------------------------------------------------------

    #[Test]
    public function get_calls_product_categories_id_endpoint_and_returns_dto(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => $this->categoryPayload('cat-42'),
            ]))],
            $captured,
        );

        $category = (new ProductCategoryResource($http))->get('cat-42');

        $this->assertInstanceOf(ProductCategory::class, $category);
        $this->assertSame('cat-42', $category->id);
        $this->assertSame('Conseil', $category->name);

        $this->assertSame('GET', $captured[0]->getMethod());
        $this->assertStringContainsString('/product-categories/cat-42', (string) $captured[0]->getUri());
    }

    // -------------------------------------------------------------------------
    // create()
    // -------------------------------------------------------------------------

    #[Test]
    public function create_posts_product_categories_and_returns_dto(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(201, ['Content-Type' => 'application/json'], json_encode([
                'data' => $this->categoryPayload('cat-new'),
            ]))],
            $captured,
        );

        $category = (new ProductCategoryResource($http))->create([
            'name'  => 'Conseil',
            'color' => '#0066FF',
        ]);

        $this->assertSame('cat-new', $category->id);

        $this->assertSame('POST', $captured[0]->getMethod());
        $this->assertStringContainsString('/product-categories', (string) $captured[0]->getUri());

        $body = json_decode((string) $captured[0]->getBody(), true);
        $this->assertSame('Conseil', $body['name']);
        $this->assertSame('#0066FF', $body['color']);
    }

    // -------------------------------------------------------------------------
    // update() — PATCH
    // -------------------------------------------------------------------------

    #[Test]
    public function update_patches_product_category_and_returns_dto(): void
    {
        $captured = [];
        $payload = $this->categoryPayload('cat-7');
        $payload['name'] = 'Conseil strategique';
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => $payload,
            ]))],
            $captured,
        );

        $category = (new ProductCategoryResource($http))->update('cat-7', [
            'name' => 'Conseil strategique',
        ]);

        $this->assertSame('Conseil strategique', $category->name);

        $this->assertSame('PATCH', $captured[0]->getMethod());
        $this->assertStringContainsString('/product-categories/cat-7', (string) $captured[0]->getUri());

        $body = json_decode((string) $captured[0]->getBody(), true);
        $this->assertSame('Conseil strategique', $body['name']);
    }

    // -------------------------------------------------------------------------
    // replace() — PUT
    // -------------------------------------------------------------------------

    #[Test]
    public function replace_puts_product_category_and_returns_dto(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => $this->categoryPayload('cat-9'),
            ]))],
            $captured,
        );

        (new ProductCategoryResource($http))->replace('cat-9', [
            'name'     => 'Conseil',
            'position' => 3,
        ]);

        $this->assertSame('PUT', $captured[0]->getMethod());
        $this->assertStringContainsString('/product-categories/cat-9', (string) $captured[0]->getUri());

        $body = json_decode((string) $captured[0]->getBody(), true);
        $this->assertSame(3, $body['position']);
    }

    // -------------------------------------------------------------------------
    // delete()
    // -------------------------------------------------------------------------

    #[Test]
    public function delete_calls_product_categories_id_endpoint(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(204, [], '')],
            $captured,
        );

        (new ProductCategoryResource($http))->delete('cat-x');

        $this->assertSame('DELETE', $captured[0]->getMethod());
        $this->assertStringContainsString('/product-categories/cat-x', (string) $captured[0]->getUri());
    }

    // -------------------------------------------------------------------------
    // DTO mapping
    // -------------------------------------------------------------------------

    #[Test]
    public function category_dto_maps_all_fields_from_api_payload(): void
    {
        $category = ProductCategory::fromArray($this->categoryPayload('cat-dto'));

        $this->assertSame('cat-dto', $category->id);
        $this->assertSame('tenant-uuid', $category->tenantId);
        $this->assertNull($category->subTenantId);
        $this->assertSame('Conseil', $category->name);
        $this->assertSame('#0066FF', $category->color);
        $this->assertSame('Prestations de conseil', $category->description);
        $this->assertSame(1, $category->position);
        $this->assertSame(4, $category->productsCount);
        $this->assertSame(['ref' => 'C-001'], $category->metadata);
        $this->assertNotNull($category->createdAt);
        $this->assertNotNull($category->updatedAt);
    }

    #[Test]
    public function category_dto_derives_defaults_for_minimal_payload(): void
    {
        $category = ProductCategory::fromArray([
            'id'        => 'cat-min',
            'tenant_id' => 'tenant-uuid',
            'name'      => 'Divers',
        ]);

        $this->assertSame('cat-min', $category->id);
        $this->assertSame('Divers', $category->name);
        $this->assertSame(0, $category->position);
        $this->assertNull($category->color);
        $this->assertNull($category->description);
        $this->assertNull($category->productsCount);
        $this->assertNull($category->metadata);
        $this->assertNull($category->createdAt);
    }
}
