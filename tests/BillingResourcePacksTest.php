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
use Scell\Sdk\DTOs\CreditPack;
use Scell\Sdk\Http\HttpClient;
use Scell\Sdk\Resources\BillingResource;

/**
 * Couvre les nouvelles methodes `BillingResource::listPacks()` et
 * `BillingResource::checkoutPack()` (SDK 2.24.0).
 */
class BillingResourcePacksTest extends TestCase
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

    /**
     * @return array<string, mixed>
     */
    private function packPayload(string $slug = 'pro'): array
    {
        return [
            'id' => "uuid-{$slug}",
            'slug' => $slug,
            'name' => "Pack {$slug}",
            'description' => 'Bonus +10%',
            'amount_eur' => 10000,
            'amount_euros' => 100.00,
            'credits_eur' => 11000,
            'credits_euros' => 110.00,
            'bonus_eur' => 1000,
            'bonus_percent' => 10.0,
            'currency' => 'EUR',
            'position' => 2,
            'is_recommended' => true,
        ];
    }

    #[Test]
    public function list_packs_calls_tenant_billing_packs_endpoint(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => [
                    $this->packPayload('starter'),
                    $this->packPayload('pro'),
                ],
            ]))],
            $captured,
        );

        $packs = (new BillingResource($http))->listPacks();

        $this->assertCount(1, $captured);
        $this->assertSame('GET', $captured[0]->getMethod());
        $this->assertSame(
            'https://api.scell.io/api/v1/tenant/billing/packs',
            (string) $captured[0]->getUri()->withQuery(''),
        );

        $this->assertCount(2, $packs);
        $this->assertContainsOnlyInstancesOf(CreditPack::class, $packs);
        $this->assertSame('starter', $packs[0]->slug);
        $this->assertSame('pro', $packs[1]->slug);
    }

    #[Test]
    public function checkout_pack_posts_to_tenant_billing_packs_slug_checkout(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'success' => true,
                'mode' => 'live',
                'client_secret' => 'pi_xxx_secret_yyy',
                'payment_intent_id' => 'pi_xxx',
            ]))],
            $captured,
        );

        $response = (new BillingResource($http))->checkoutPack('pro');

        $this->assertSame('POST', $captured[0]->getMethod());
        $this->assertSame(
            'https://api.scell.io/api/v1/tenant/billing/packs/pro/checkout',
            (string) $captured[0]->getUri()->withQuery(''),
        );

        // Le body POST est vide (le checkout n'a pas de params)
        $this->assertSame('[]', (string) $captured[0]->getBody());

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
        $this->assertSame('live', $response['mode']);
        $this->assertSame('pi_xxx_secret_yyy', $response['client_secret']);
    }

    #[Test]
    public function checkout_pack_handles_sandbox_bypass_response(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(201, ['Content-Type' => 'application/json'], json_encode([
                'success' => true,
                'mode' => 'sandbox_bypass',
                'credit_eur' => 11000,
                'balance_after' => 11000,
            ]))],
            $captured,
        );

        $response = (new BillingResource($http))->checkoutPack('starter');

        $this->assertSame('sandbox_bypass', $response['mode']);
        $this->assertSame(11000, $response['credit_eur']);
    }

    #[Test]
    public function list_packs_handles_empty_response(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => [],
            ]))],
            $captured,
        );

        $packs = (new BillingResource($http))->listPacks();

        $this->assertSame([], $packs);
    }
}
