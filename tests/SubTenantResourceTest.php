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
use Scell\Sdk\DTOs\SuperPDPAuthorizeUrl;
use Scell\Sdk\Http\HttpClient;
use Scell\Sdk\Resources\SubTenantResource;

/**
 * Couvre les nouveautes SDK v2.9.0 :
 *  - POST /tenant/sub-tenants/{id}/superpdp-authorize -> SuperPDPAuthorizeUrl
 *  - DELETE /tenant/sub-tenants/{id}?cascade=true -> array{companies_deleted}
 */
class SubTenantResourceTest extends TestCase
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
            'handler' => $stack,
            'http_errors' => false,
        ]));

        return $http;
    }

    #[Test]
    public function superpdp_authorize_calls_correct_endpoint_and_returns_dto(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'authorize_url' => 'https://oauth.superpdp.tech/authorize?client_id=xxx&state=abc',
                'state' => 'abc123',
            ]))],
            $captured,
        );

        $result = (new SubTenantResource($http))->superpdpAuthorize('sub-uuid-42');

        $this->assertCount(1, $captured);
        $this->assertSame('POST', $captured[0]->getMethod());
        $this->assertSame(
            'https://api.scell.io/api/v1/tenant/sub-tenants/sub-uuid-42/superpdp-authorize',
            (string) $captured[0]->getUri()->withQuery('')
        );

        $this->assertInstanceOf(SuperPDPAuthorizeUrl::class, $result);
        $this->assertSame('https://oauth.superpdp.tech/authorize?client_id=xxx&state=abc', $result->authorizeUrl);
        $this->assertSame('abc123', $result->state);
    }

    #[Test]
    public function delete_without_options_omits_cascade_query(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'message' => 'Sub-tenant supprime',
            ]))],
            $captured,
        );

        $result = (new SubTenantResource($http))->delete('sub-uuid-1');

        $this->assertCount(1, $captured);
        $this->assertSame('DELETE', $captured[0]->getMethod());
        $this->assertSame(
            'https://api.scell.io/api/v1/tenant/sub-tenants/sub-uuid-1',
            (string) $captured[0]->getUri()->withQuery('')
        );
        $this->assertSame('', $captured[0]->getUri()->getQuery());
        $this->assertSame('Sub-tenant supprime', $result['message']);
    }

    #[Test]
    public function delete_with_cascade_option_adds_query_param(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'message' => 'Sub-tenant supprime',
                'companies_deleted' => 2,
            ]))],
            $captured,
        );

        $result = (new SubTenantResource($http))->delete('sub-uuid-2', ['cascade' => true]);

        $this->assertCount(1, $captured);
        parse_str($captured[0]->getUri()->getQuery(), $query);
        $this->assertSame('true', $query['cascade']);
        $this->assertSame(2, $result['companies_deleted']);
    }

    #[Test]
    public function delete_with_cascade_false_omits_query(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'message' => 'Sub-tenant supprime',
            ]))],
            $captured,
        );

        (new SubTenantResource($http))->delete('sub-uuid-3', ['cascade' => false]);

        $this->assertCount(1, $captured);
        $this->assertSame('', $captured[0]->getUri()->getQuery());
    }
}
