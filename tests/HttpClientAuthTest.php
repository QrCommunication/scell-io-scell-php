<?php

declare(strict_types=1);

namespace Scell\Sdk\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Scell\Sdk\Config;
use Scell\Sdk\Http\HttpClient;
use Scell\Sdk\ScellApiClient;
use Scell\Sdk\ScellPublicClient;

/**
 * Verifie que chaque mode d'authentification ecrit le bon header HTTP.
 *
 * Bug historique : `withApiKey('pk_live_...')` envoyait `X-API-Key: pk_live_*`
 * alors que les endpoints widget exigent `X-Publishable-Key`. Depuis v2.3.0,
 * le SDK expose `withPublishableKey()` pour utiliser le header attendu.
 */
class HttpClientAuthTest extends TestCase
{
    /**
     * @return array<string, string>
     */
    private function defaultHeaders(HttpClient $http): array
    {
        $method = new ReflectionMethod($http, 'getDefaultHeaders');
        $method->setAccessible(true);

        /** @var array<string, string> $headers */
        $headers = $method->invoke($http);

        return $headers;
    }

    #[Test]
    public function with_api_key_sets_x_api_key_header(): void
    {
        $http = new HttpClient('https://api.scell.io/api/v1');
        $http->withApiKey('sk_live_xxx');

        $headers = $this->defaultHeaders($http);

        $this->assertArrayHasKey('X-API-Key', $headers);
        $this->assertSame('sk_live_xxx', $headers['X-API-Key']);
        $this->assertArrayNotHasKey('X-Publishable-Key', $headers);
        $this->assertArrayNotHasKey('X-Tenant-Key', $headers);
        $this->assertArrayNotHasKey('Authorization', $headers);
    }

    #[Test]
    public function with_publishable_key_sets_x_publishable_key_header(): void
    {
        $http = new HttpClient('https://api.scell.io/api/v1');
        $http->withPublishableKey('pk_live_xxx');

        $headers = $this->defaultHeaders($http);

        $this->assertArrayHasKey('X-Publishable-Key', $headers);
        $this->assertSame('pk_live_xxx', $headers['X-Publishable-Key']);
        $this->assertArrayNotHasKey('X-API-Key', $headers);
        $this->assertArrayNotHasKey('X-Tenant-Key', $headers);
        $this->assertArrayNotHasKey('Authorization', $headers);
    }

    #[Test]
    public function with_tenant_key_sets_x_tenant_key_header(): void
    {
        $http = new HttpClient('https://api.scell.io/api/v1');
        $http->withTenantKey('tk_live_xxx');

        $headers = $this->defaultHeaders($http);

        $this->assertArrayHasKey('X-Tenant-Key', $headers);
        $this->assertSame('tk_live_xxx', $headers['X-Tenant-Key']);
        $this->assertArrayNotHasKey('X-API-Key', $headers);
        $this->assertArrayNotHasKey('X-Publishable-Key', $headers);
    }

    #[Test]
    public function with_bearer_token_sets_authorization_header(): void
    {
        $http = new HttpClient('https://api.scell.io/api/v1');
        $http->withBearerToken('jwt-token');

        $headers = $this->defaultHeaders($http);

        $this->assertSame('Bearer jwt-token', $headers['Authorization']);
        $this->assertArrayNotHasKey('X-API-Key', $headers);
        $this->assertArrayNotHasKey('X-Publishable-Key', $headers);
    }

    #[Test]
    public function switching_auth_mode_clears_previous_credentials(): void
    {
        $http = new HttpClient('https://api.scell.io/api/v1');
        $http->withApiKey('sk_live_xxx');
        $http->withPublishableKey('pk_live_yyy');

        $headers = $this->defaultHeaders($http);

        $this->assertArrayNotHasKey('X-API-Key', $headers);
        $this->assertSame('pk_live_yyy', $headers['X-Publishable-Key']);
    }

    #[Test]
    public function has_credentials_returns_true_for_publishable_key(): void
    {
        $http = new HttpClient('https://api.scell.io/api/v1');
        $this->assertFalse($http->hasCredentials());

        $http->withPublishableKey('pk_live_xxx');
        $this->assertTrue($http->hasCredentials());
    }

    #[Test]
    public function scell_api_client_with_publishable_key_factory_uses_correct_header(): void
    {
        $client = ScellApiClient::withPublishableKey('pk_test_xxx');

        $headers = $this->defaultHeaders($client->getHttpClient());

        $this->assertSame('pk_test_xxx', $headers['X-Publishable-Key']);
        $this->assertArrayNotHasKey('X-API-Key', $headers);
    }

    #[Test]
    public function scell_public_client_uses_publishable_key_header(): void
    {
        $client = new ScellPublicClient('pk_live_xxx');

        $headers = $this->defaultHeaders($client->getHttpClient());

        $this->assertSame('pk_live_xxx', $headers['X-Publishable-Key']);
        $this->assertArrayNotHasKey('X-API-Key', $headers);
    }

    #[Test]
    public function scell_api_client_sandbox_routes_pk_keys_to_publishable_header(): void
    {
        $client = ScellApiClient::sandbox('pk_test_xxx');

        $headers = $this->defaultHeaders($client->getHttpClient());

        $this->assertSame('pk_test_xxx', $headers['X-Publishable-Key']);
        $this->assertArrayNotHasKey('X-API-Key', $headers);
    }

    #[Test]
    public function scell_api_client_sandbox_keeps_sk_keys_on_api_key_header(): void
    {
        $client = ScellApiClient::sandbox('sk_test_xxx');

        $headers = $this->defaultHeaders($client->getHttpClient());

        $this->assertSame('sk_test_xxx', $headers['X-API-Key']);
        $this->assertArrayNotHasKey('X-Publishable-Key', $headers);
    }

    #[Test]
    public function sdk_version_constant_is_in_sync_with_release(): void
    {
        // Verrouille la constante SDK_VERSION pour eviter le drift historique
        // (etait restee a '1.12.0' alors que les tags etaient a v2.2.0).
        $this->assertSame('2.8.0', HttpClient::SDK_VERSION);
    }
}
