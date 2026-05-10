<?php

declare(strict_types=1);

namespace Scell\Sdk\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Scell\Sdk\DTOs\Signature;
use Scell\Sdk\Enums\SignatureStatus;
use Scell\Sdk\Http\HttpClient;
use Scell\Sdk\Resources\TenantSignatureResource;
use Scell\Sdk\ScellApiClient;
use Scell\Sdk\ScellTenantClient;

/**
 * Couvre les 4 endpoints URL-nested introduits en SDK v2.7.0 :
 *  - GET /tenant/signatures
 *  - GET /tenant/signatures/{id}
 *  - GET /tenant/sub-tenants/{subTenantId}/signatures
 *  - GET /tenant/sub-tenants/{subTenantId}/signatures/{id}
 *
 * Auth backend : `tenant.key` (header `X-API-Key sk_*`, sans contrainte
 * `company_id`). Le SDK doit emettre les bons paths + query params.
 */
class TenantSignatureResourceTest extends TestCase
{
    /**
     * Cree un HttpClient avec un MockHandler injecte par reflection sur
     * la propriete Guzzle privee. Permet de capturer les requetes sans
     * toucher au reseau.
     *
     * @param Response[]    $responses
     * @param Request[]     $captured  Reference, rempli par le test.
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
            'handler' => $stack,
            'http_errors' => false,
        ]));

        return $http;
    }

    /**
     * Payload signature minimal accepte par Signature::fromArray.
     *
     * @return array<string, mixed>
     */
    private function signatureFixture(string $id = 'sig-1'): array
    {
        return [
            'id' => $id,
            'title' => 'Contrat',
            'document_name' => 'contrat.pdf',
            'document_size' => 1024,
            'signers' => [],
            'status' => 'completed',
            'environment' => 'production',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paginatedFixture(array $items): array
    {
        return [
            'data' => $items,
            'meta' => [
                'current_page' => 1,
                'per_page' => 25,
                'total' => count($items),
                'last_page' => 1,
            ],
        ];
    }

    #[Test]
    public function list_calls_tenant_signatures_endpoint(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode(
                $this->paginatedFixture([$this->signatureFixture('sig-1')])
            ))],
            $captured,
        );

        $resource = new TenantSignatureResource($http);
        $result = $resource->list([
            'status' => SignatureStatus::Completed,
            'environment' => 'production',
            'per_page' => 50,
        ]);

        $this->assertCount(1, $captured);
        $this->assertSame('GET', $captured[0]->getMethod());
        $this->assertSame(
            'https://api.scell.io/api/v1/tenant/signatures',
            (string) $captured[0]->getUri()->withQuery('')
        );

        parse_str($captured[0]->getUri()->getQuery(), $query);
        $this->assertSame('completed', $query['status']);
        $this->assertSame('production', $query['environment']);
        $this->assertSame('50', $query['per_page']);

        $this->assertCount(1, $result->data);
        $this->assertInstanceOf(Signature::class, $result->data[0]);
        $this->assertSame('sig-1', $result->data[0]->id);
    }

    #[Test]
    public function get_calls_tenant_signature_detail_endpoint(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => $this->signatureFixture('sig-42'),
            ]))],
            $captured,
        );

        $signature = (new TenantSignatureResource($http))->get('sig-42');

        $this->assertCount(1, $captured);
        $this->assertSame(
            'https://api.scell.io/api/v1/tenant/signatures/sig-42',
            (string) $captured[0]->getUri()->withQuery('')
        );
        $this->assertSame('sig-42', $signature->id);
    }

    #[Test]
    public function list_for_sub_tenant_calls_nested_endpoint(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode(
                $this->paginatedFixture([])
            ))],
            $captured,
        );

        (new TenantSignatureResource($http))->listForSubTenant('sub-uuid', [
            'status' => 'pending',
        ]);

        $this->assertCount(1, $captured);
        $this->assertSame(
            'https://api.scell.io/api/v1/tenant/sub-tenants/sub-uuid/signatures',
            (string) $captured[0]->getUri()->withQuery('')
        );

        parse_str($captured[0]->getUri()->getQuery(), $query);
        $this->assertSame('pending', $query['status']);
    }

    #[Test]
    public function get_for_sub_tenant_calls_nested_detail_endpoint(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => $this->signatureFixture('sig-99'),
            ]))],
            $captured,
        );

        $signature = (new TenantSignatureResource($http))
            ->getForSubTenant('sub-uuid', 'sig-99');

        $this->assertCount(1, $captured);
        $this->assertSame(
            'https://api.scell.io/api/v1/tenant/sub-tenants/sub-uuid/signatures/sig-99',
            (string) $captured[0]->getUri()->withQuery('')
        );
        $this->assertSame('sig-99', $signature->id);
    }

    #[Test]
    public function tenant_client_exposes_signatures_resource(): void
    {
        $tenantClient = ScellTenantClient::create('tk_test_xxx');

        $this->assertInstanceOf(
            TenantSignatureResource::class,
            $tenantClient->signatures()
        );
        // Memoization : meme instance retournee.
        $this->assertSame(
            $tenantClient->signatures(),
            $tenantClient->signatures()
        );
    }

    #[Test]
    public function api_client_exposes_tenant_signatures_resource(): void
    {
        $api = ScellApiClient::withApiKey('sk_test_xxx');

        $this->assertInstanceOf(
            TenantSignatureResource::class,
            $api->tenantSignatures()
        );
        $this->assertSame(
            $api->tenantSignatures(),
            $api->tenantSignatures()
        );
    }
}
