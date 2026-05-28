<?php

declare(strict_types=1);

namespace Scell\Sdk\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Scell\Sdk\Config;
use Scell\Sdk\Resources\CreditPacksResource;
use Scell\Sdk\ScellApiClient;
use Scell\Sdk\ScellClient;
use Scell\Sdk\ScellPublicClient;

/**
 * Verifie que la nouvelle `CreditPacksResource` (SDK 2.24.0) est correctement
 * wire-uppee sur les 3 clients :
 *  - ScellApiClient (sk_*, pk_*)
 *  - ScellPublicClient (pk_*)
 *  - ScellClient (Bearer Sanctum dashboard)
 *
 * Verifie aussi que les instances sont mises en cache (singleton par client).
 */
class CreditPacksWiringTest extends TestCase
{
    #[Test]
    public function scell_api_client_exposes_credit_packs_resource(): void
    {
        $client = ScellApiClient::withApiKey('sk_test_xxx', Config::local('http://localhost/api/v1'));

        $packs = $client->creditPacks();

        $this->assertInstanceOf(CreditPacksResource::class, $packs);
    }

    #[Test]
    public function scell_api_client_caches_credit_packs_instance(): void
    {
        $client = ScellApiClient::withApiKey('sk_test_xxx', Config::local('http://localhost/api/v1'));

        $first = $client->creditPacks();
        $second = $client->creditPacks();

        $this->assertSame($first, $second, 'creditPacks() should return the same instance on repeated calls');
    }

    #[Test]
    public function scell_public_client_exposes_credit_packs_resource(): void
    {
        $client = new ScellPublicClient('pk_test_xxx', Config::local('http://localhost/api/v1'));

        $packs = $client->creditPacks();

        $this->assertInstanceOf(CreditPacksResource::class, $packs);
    }

    #[Test]
    public function scell_client_dashboard_exposes_credit_packs_resource(): void
    {
        $client = new ScellClient('bearer-token-xxx', Config::local('http://localhost/api/v1'));

        $packs = $client->creditPacks();

        $this->assertInstanceOf(CreditPacksResource::class, $packs);
    }
}
