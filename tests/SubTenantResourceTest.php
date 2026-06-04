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

    #[Test]
    public function get_thresholds_calls_endpoint_and_returns_report(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => [
                    'sub_tenant_id' => 'sub-uuid-42',
                    'tenant_id' => 'tenant-1',
                    'fiscal_year' => 2026,
                    'generated_at' => '2026-06-04T10:00:00+00:00',
                    'gauges' => [[
                        'category' => 'service',
                        'kind' => 'vat_franchise_base',
                        'revenue' => 30000,
                        'threshold' => 37500,
                        'percent' => 80,
                        'level' => 'warning_80',
                        'actionable' => false,
                        'projected_crossing_date' => '2026-11-01',
                    ]],
                    'new_alerts' => [],
                ],
                'disclaimer' => 'Information non contractuelle...',
            ]))],
            $captured,
        );

        $result = (new SubTenantResource($http))->getThresholds('sub-uuid-42');

        $this->assertSame('GET', $captured[0]->getMethod());
        $this->assertSame(
            'https://api.scell.io/api/v1/tenant/sub-tenants/sub-uuid-42/thresholds',
            (string) $captured[0]->getUri()->withQuery('')
        );
        $this->assertSame('service', $result['data']['gauges'][0]['category']);
        $this->assertSame('warning_80', $result['data']['gauges'][0]['level']);
        $this->assertArrayHasKey('disclaimer', $result);
    }

    #[Test]
    public function update_fiscal_status_patches_endpoint_and_returns_sub_tenant(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => ['id' => 'sub-uuid-42', 'name' => 'AE', 'onboarding_status' => 'active', 'vat_status' => 'liable'],
                'message' => 'Statut mis a jour : les prochaines factures porteront la TVA.',
                'disclaimer' => 'Information non contractuelle...',
            ]))],
            $captured,
        );

        $result = (new SubTenantResource($http))->updateFiscalStatus('sub-uuid-42', [
            'vat_status' => 'liable',
            'vat_number' => 'FR12345678901',
        ]);

        $this->assertSame('PATCH', $captured[0]->getMethod());
        $this->assertSame(
            'https://api.scell.io/api/v1/tenant/sub-tenants/sub-uuid-42/fiscal-status',
            (string) $captured[0]->getUri()->withQuery('')
        );
        $this->assertSame('liable', json_decode((string) $captured[0]->getBody(), true)['vat_status']);
        $this->assertInstanceOf(\Scell\Sdk\DTOs\SubTenant::class, $result);
    }

    #[Test]
    public function simulate_thresholds_posts_endpoint_with_amount_and_category(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => ['sub_tenant_id' => 'sub-uuid-42', 'fiscal_year' => 2026, 'gauges' => [], 'new_alerts' => []],
                'simulated' => ['amount' => 5000, 'category' => 'service'],
                'disclaimer' => 'Information non contractuelle...',
            ]))],
            $captured,
        );

        $result = (new SubTenantResource($http))->simulateThresholds('sub-uuid-42', [
            'amount' => 5000,
            'category' => 'service',
        ]);

        $this->assertSame('POST', $captured[0]->getMethod());
        $this->assertSame(
            'https://api.scell.io/api/v1/tenant/sub-tenants/sub-uuid-42/thresholds/simulate',
            (string) $captured[0]->getUri()->withQuery('')
        );
        $this->assertSame(5000, json_decode((string) $captured[0]->getBody(), true)['amount']);
        $this->assertSame('service', $result['simulated']['category']);
    }
}
