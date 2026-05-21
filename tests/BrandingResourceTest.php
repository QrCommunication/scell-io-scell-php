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
use Scell\Sdk\DTOs\Branding;
use Scell\Sdk\Http\HttpClient;
use Scell\Sdk\Resources\BrandingResource;

/**
 * Couvre les nouvelles methodes SDK v2.13.0 pour le branding.
 */
class BrandingResourceTest extends TestCase
{
    private const SUB_TENANT_ID = 'sub-uuid-99';

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

    private function makeBrandingPayload(bool $complete = false): array
    {
        return [
            'logo_url' => $complete ? 'https://cdn.scell.io/logos/tenant.png' : null,
            'primary_color' => $complete ? '#1a73e8' : null,
            'email_footer' => $complete ? 'Ma Societe SAS — SIRET 123 456 789 00010' : null,
            'email_signature' => "L'equipe Ma Societe",
            'is_complete' => $complete,
            'scope' => 'tenant',
            'entity_id' => 'tenant-uuid-01',
            'updated_at' => '2026-05-21T10:00:00Z',
        ];
    }

    // -------------------------------------------------------------------------
    // getTenant()
    // -------------------------------------------------------------------------

    #[Test]
    public function get_tenant_calls_correct_endpoint_and_returns_branding_dto(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => $this->makeBrandingPayload(false),
            ]))],
            $captured,
        );

        $branding = (new BrandingResource($http))->getTenant();

        $this->assertCount(1, $captured);
        $this->assertSame('GET', $captured[0]->getMethod());
        $this->assertSame(
            'https://api.scell.io/api/v1/branding/tenant',
            (string) $captured[0]->getUri()->withQuery(''),
        );

        $this->assertInstanceOf(Branding::class, $branding);
        $this->assertNull($branding->logoUrl);
        $this->assertFalse($branding->isComplete);
        $this->assertFalse($branding->isReady());
        $this->assertSame('tenant', $branding->scope);
    }

    // -------------------------------------------------------------------------
    // updateTenant()
    // -------------------------------------------------------------------------

    #[Test]
    public function update_tenant_calls_patch_and_returns_branding_dto(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => $this->makeBrandingPayload(true),
            ]))],
            $captured,
        );

        $branding = (new BrandingResource($http))->updateTenant([
            'primary_color' => '#1a73e8',
            'email_footer' => 'Ma Societe SAS — SIRET 123 456 789 00010',
        ]);

        $this->assertSame('PATCH', $captured[0]->getMethod());
        $this->assertSame(
            'https://api.scell.io/api/v1/branding/tenant',
            (string) $captured[0]->getUri()->withQuery(''),
        );

        $this->assertInstanceOf(Branding::class, $branding);
        $this->assertTrue($branding->isComplete);
        $this->assertSame('#1a73e8', $branding->primaryColor);
        $this->assertTrue($branding->isReady());
    }

    // -------------------------------------------------------------------------
    // getSubTenant()
    // -------------------------------------------------------------------------

    #[Test]
    public function get_sub_tenant_calls_correct_endpoint_with_id(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => array_merge($this->makeBrandingPayload(false), ['scope' => 'sub_tenant']),
            ]))],
            $captured,
        );

        $branding = (new BrandingResource($http))->getSubTenant(self::SUB_TENANT_ID);

        $this->assertSame('GET', $captured[0]->getMethod());
        $this->assertSame(
            'https://api.scell.io/api/v1/branding/sub-tenants/' . self::SUB_TENANT_ID,
            (string) $captured[0]->getUri()->withQuery(''),
        );

        $this->assertInstanceOf(Branding::class, $branding);
        $this->assertSame('sub_tenant', $branding->scope);
    }

    // -------------------------------------------------------------------------
    // updateSubTenant()
    // -------------------------------------------------------------------------

    #[Test]
    public function update_sub_tenant_calls_patch_with_correct_url(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => array_merge($this->makeBrandingPayload(true), ['scope' => 'sub_tenant']),
            ]))],
            $captured,
        );

        $branding = (new BrandingResource($http))->updateSubTenant(self::SUB_TENANT_ID, [
            'logo_url' => 'https://cdn.example.com/logo.png',
        ]);

        $this->assertSame('PATCH', $captured[0]->getMethod());
        $this->assertSame(
            'https://api.scell.io/api/v1/branding/sub-tenants/' . self::SUB_TENANT_ID,
            (string) $captured[0]->getUri()->withQuery(''),
        );

        $this->assertInstanceOf(Branding::class, $branding);
    }

    // -------------------------------------------------------------------------
    // logoUploadUrlTenant()
    // -------------------------------------------------------------------------

    #[Test]
    public function logo_upload_url_tenant_calls_post_and_returns_upload_data(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'url' => 'https://s3.example.com/presigned-upload-url?token=abc',
                'public_url' => 'https://cdn.scell.io/logos/tenant-uuid.png',
                'expires_at' => '2026-05-21T11:00:00Z',
            ]))],
            $captured,
        );

        $result = (new BrandingResource($http))->logoUploadUrlTenant('image/png');

        $this->assertSame('POST', $captured[0]->getMethod());
        $this->assertSame(
            'https://api.scell.io/api/v1/branding/tenant/logo-upload-url',
            (string) $captured[0]->getUri()->withQuery(''),
        );

        $body = json_decode($captured[0]->getBody()->getContents(), true);
        $this->assertSame('image/png', $body['mime_type']);

        $this->assertArrayHasKey('url', $result);
        $this->assertArrayHasKey('public_url', $result);
    }

    // -------------------------------------------------------------------------
    // logoUploadUrlSubTenant()
    // -------------------------------------------------------------------------

    #[Test]
    public function logo_upload_url_sub_tenant_calls_correct_url(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'url' => 'https://s3.example.com/presigned-url',
                'public_url' => 'https://cdn.scell.io/logos/sub.png',
                'expires_at' => '2026-05-21T11:00:00Z',
            ]))],
            $captured,
        );

        $result = (new BrandingResource($http))->logoUploadUrlSubTenant(self::SUB_TENANT_ID, 'image/jpeg');

        $this->assertSame('POST', $captured[0]->getMethod());
        $this->assertSame(
            'https://api.scell.io/api/v1/branding/sub-tenants/' . self::SUB_TENANT_ID . '/logo-upload-url',
            (string) $captured[0]->getUri()->withQuery(''),
        );

        $this->assertArrayHasKey('public_url', $result);
    }

    // -------------------------------------------------------------------------
    // Branding DTO helpers
    // -------------------------------------------------------------------------

    #[Test]
    public function branding_dto_is_ready_returns_true_only_when_all_fields_set(): void
    {
        $incomplete = Branding::fromArray($this->makeBrandingPayload(false));
        $this->assertFalse($incomplete->isReady());

        $complete = Branding::fromArray($this->makeBrandingPayload(true));
        $this->assertTrue($complete->isReady());
        $this->assertTrue($complete->isComplete);
        $this->assertSame('https://cdn.scell.io/logos/tenant.png', $complete->logoUrl);
        $this->assertSame('#1a73e8', $complete->primaryColor);
    }
}
