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
            'brand_email_enabled' => true,
            'computed_email_footer' => 'Ma Societe SAS — SIRET 123 456 789 00010 — 1 rue de la Paix, 75002 Paris',
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
    // uploadLogoTenant() — upload direct multipart (v3.4.0)
    // -------------------------------------------------------------------------

    #[Test]
    public function upload_logo_tenant_posts_multipart_and_hydrates_flat_branding(): void
    {
        $captured = [];
        // Reponse A PLAT (sans wrapper 'data'), conforme au backend.
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode(
                $this->makeBrandingPayload(true),
            ))],
            $captured,
        );

        $logo = fopen('php://memory', 'r+b');
        fwrite($logo, 'fake-png-bytes');
        rewind($logo);

        $branding = (new BrandingResource($http))->uploadLogoTenant($logo, 'logo.png');

        $this->assertCount(1, $captured);
        $this->assertSame('POST', $captured[0]->getMethod());
        $this->assertSame(
            'https://api.scell.io/api/v1/branding/tenant/logo',
            (string) $captured[0]->getUri()->withQuery(''),
        );
        $this->assertStringStartsWith('multipart/form-data', $captured[0]->getHeaderLine('Content-Type'));

        $body = (string) $captured[0]->getBody();
        $this->assertStringContainsString('name="logo"', $body);
        $this->assertStringContainsString('filename="logo.png"', $body);
        $this->assertStringContainsString('fake-png-bytes', $body);

        $this->assertInstanceOf(Branding::class, $branding);
        $this->assertSame('https://cdn.scell.io/logos/tenant.png', $branding->logoUrl);
        $this->assertTrue($branding->isComplete);
    }

    #[Test]
    public function upload_logo_tenant_accepts_a_file_path(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode(
                $this->makeBrandingPayload(true),
            ))],
            $captured,
        );

        $path = tempnam(sys_get_temp_dir(), 'scell-logo-') . '.png';
        file_put_contents($path, 'png-from-path');

        try {
            $branding = (new BrandingResource($http))->uploadLogoTenant($path);

            $body = (string) $captured[0]->getBody();
            $this->assertStringContainsString('filename="' . basename($path) . '"', $body);
            $this->assertStringContainsString('png-from-path', $body);
            $this->assertInstanceOf(Branding::class, $branding);
        } finally {
            @unlink($path);
        }
    }

    // -------------------------------------------------------------------------
    // uploadLogoSubTenant() — upload direct multipart scope sub-tenant (v3.4.0)
    // -------------------------------------------------------------------------

    #[Test]
    public function upload_logo_sub_tenant_posts_multipart_to_scoped_url(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode(
                array_merge($this->makeBrandingPayload(true), ['scope' => 'sub_tenant']),
            ))],
            $captured,
        );

        $logo = fopen('php://memory', 'r+b');
        fwrite($logo, 'fake-svg-bytes');
        rewind($logo);

        $branding = (new BrandingResource($http))->uploadLogoSubTenant(self::SUB_TENANT_ID, $logo, 'logo.svg');

        $this->assertSame('POST', $captured[0]->getMethod());
        $this->assertSame(
            'https://api.scell.io/api/v1/branding/sub-tenants/' . self::SUB_TENANT_ID . '/logo',
            (string) $captured[0]->getUri()->withQuery(''),
        );
        $this->assertStringStartsWith('multipart/form-data', $captured[0]->getHeaderLine('Content-Type'));

        $body = (string) $captured[0]->getBody();
        $this->assertStringContainsString('name="logo"', $body);
        $this->assertStringContainsString('filename="logo.svg"', $body);

        $this->assertInstanceOf(Branding::class, $branding);
        $this->assertSame('sub_tenant', $branding->scope);
    }

    // -------------------------------------------------------------------------
    // previewTenant() / previewSubTenant() — overrides non persistes (v3.4.0)
    // -------------------------------------------------------------------------

    #[Test]
    public function preview_tenant_without_overrides_has_empty_query(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'text/html'], '<html>preview</html>')],
            $captured,
        );

        $html = (new BrandingResource($http))->previewTenant();

        $this->assertSame('GET', $captured[0]->getMethod());
        $this->assertSame(
            'https://api.scell.io/api/v1/branding/tenant/preview',
            (string) $captured[0]->getUri()->withQuery(''),
        );
        $this->assertSame('', $captured[0]->getUri()->getQuery());
        $this->assertSame('<html>preview</html>', $html);
    }

    #[Test]
    public function preview_tenant_passes_overrides_as_query_params(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'text/html'], '<html>override</html>')],
            $captured,
        );

        $html = (new BrandingResource($http))->previewTenant([
            'brand_primary_color' => '#e63946',
            'brand_email_footer' => 'Footer de test',
            'brand_logo_url' => 'https://cdn.example.com/logo.png',
        ]);

        $query = $captured[0]->getUri()->getQuery();
        parse_str($query, $params);

        $this->assertSame('#e63946', $params['brand_primary_color']);
        $this->assertSame('Footer de test', $params['brand_email_footer']);
        $this->assertSame('https://cdn.example.com/logo.png', $params['brand_logo_url']);
        $this->assertSame('<html>override</html>', $html);
    }

    #[Test]
    public function preview_sub_tenant_passes_overrides_as_query_params(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'text/html'], '<html>sub</html>')],
            $captured,
        );

        $html = (new BrandingResource($http))->previewSubTenant(self::SUB_TENANT_ID, [
            'brand_email_signature' => 'Signature de test',
        ]);

        $this->assertSame(
            'https://api.scell.io/api/v1/branding/sub-tenants/' . self::SUB_TENANT_ID . '/preview',
            (string) $captured[0]->getUri()->withQuery(''),
        );

        parse_str($captured[0]->getUri()->getQuery(), $params);
        $this->assertSame('Signature de test', $params['brand_email_signature']);
        $this->assertSame('<html>sub</html>', $html);
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

    #[Test]
    public function branding_dto_hydrates_email_enabled_and_computed_footer(): void
    {
        $branding = Branding::fromArray(array_merge($this->makeBrandingPayload(true), [
            'brand_email_enabled' => false,
        ]));

        $this->assertFalse($branding->brandEmailEnabled);
        $this->assertSame(
            'Ma Societe SAS — SIRET 123 456 789 00010 — 1 rue de la Paix, 75002 Paris',
            $branding->computedEmailFooter,
        );
    }

    #[Test]
    public function branding_dto_new_fields_default_to_null_on_legacy_payloads(): void
    {
        $legacy = $this->makeBrandingPayload(true);
        unset($legacy['brand_email_enabled'], $legacy['computed_email_footer']);

        $branding = Branding::fromArray($legacy);

        $this->assertNull($branding->brandEmailEnabled);
        $this->assertNull($branding->computedEmailFooter);
    }
}
