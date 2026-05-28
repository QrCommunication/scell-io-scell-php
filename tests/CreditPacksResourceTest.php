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
use Scell\Sdk\Exceptions\ScellException;
use Scell\Sdk\Http\HttpClient;
use Scell\Sdk\Resources\CreditPacksResource;

/**
 * Couvre la nouvelle resource CreditPacksResource (SDK 2.24.0).
 *
 * Endpoint backend : `GET /api/v1/packs/public` (no auth required, accepts
 * publishable key or sk_*).
 */
class CreditPacksResourceTest extends TestCase
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
        $http->withPublishableKey('pk_test_xxx');

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
    private function packPayload(string $slug = 'pro', int $position = 2, bool $recommended = true): array
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
            'bonus_euros' => 10.00,
            'bonus_percent' => 10.0,
            'currency' => 'EUR',
            'position' => $position,
            'is_recommended' => $recommended,
        ];
    }

    #[Test]
    public function list_calls_packs_public_endpoint_and_returns_dtos(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => [
                    $this->packPayload('starter', 1, false),
                    $this->packPayload('pro', 2, true),
                    $this->packPayload('business', 3, false),
                ],
            ]))],
            $captured,
        );

        $packs = (new CreditPacksResource($http))->list();

        $this->assertCount(1, $captured);
        $this->assertSame('GET', $captured[0]->getMethod());
        $this->assertSame(
            'https://api.scell.io/api/v1/packs/public',
            (string) $captured[0]->getUri()->withQuery(''),
        );

        $this->assertCount(3, $packs);
        $this->assertContainsOnlyInstancesOf(CreditPack::class, $packs);

        $this->assertSame('starter', $packs[0]->slug);
        $this->assertSame('pro', $packs[1]->slug);
        $this->assertTrue($packs[1]->isRecommended);
        $this->assertFalse($packs[0]->isRecommended);
    }

    #[Test]
    public function list_handles_empty_response(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => [],
            ]))],
            $captured,
        );

        $packs = (new CreditPacksResource($http))->list();

        $this->assertSame([], $packs);
    }

    #[Test]
    public function get_filters_by_slug_and_returns_matching_pack(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => [
                    $this->packPayload('starter', 1, false),
                    $this->packPayload('pro', 2, true),
                ],
            ]))],
            $captured,
        );

        $pro = (new CreditPacksResource($http))->get('pro');

        $this->assertInstanceOf(CreditPack::class, $pro);
        $this->assertSame('pro', $pro->slug);
        $this->assertTrue($pro->isRecommended);
    }

    #[Test]
    public function get_throws_404_when_slug_not_found(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => [
                    $this->packPayload('starter', 1, false),
                ],
            ]))],
            $captured,
        );

        $this->expectException(ScellException::class);
        $this->expectExceptionMessageMatches("/inexistent.+not found/i");

        (new CreditPacksResource($http))->get('inexistent');
    }

    #[Test]
    public function credit_pack_dto_maps_all_fields_from_api_payload(): void
    {
        $pack = CreditPack::fromArray($this->packPayload('pro'));

        $this->assertSame('uuid-pro', $pack->id);
        $this->assertSame('pro', $pack->slug);
        $this->assertSame('Pack pro', $pack->name);
        $this->assertSame('Bonus +10%', $pack->description);
        $this->assertSame(10000, $pack->amountEur);
        $this->assertSame(100.00, $pack->amountEuros);
        $this->assertSame(11000, $pack->creditsEur);
        $this->assertSame(110.00, $pack->creditsEuros);
        $this->assertSame(1000, $pack->bonusEur);
        $this->assertSame(10.0, $pack->bonusPercent);
        $this->assertSame('EUR', $pack->currency);
        $this->assertSame(2, $pack->position);
        $this->assertTrue($pack->isRecommended);
        $this->assertSame(10.0, $pack->bonusEuros);
    }

    #[Test]
    public function credit_pack_dto_derives_missing_optional_fields(): void
    {
        // Backend ancien qui n'expose pas bonus_eur / bonus_euros — DTO doit deriver
        $minimal = [
            'id' => 'uuid-min',
            'slug' => 'starter',
            'name' => 'Pack starter',
            'amount_eur' => 5000,
            'credits_eur' => 5000, // pas de bonus
        ];

        $pack = CreditPack::fromArray($minimal);

        $this->assertSame(0, $pack->bonusEur);
        $this->assertFalse($pack->hasBonus());
        $this->assertSame(0.0, $pack->bonusPercent);
        $this->assertSame(0, $pack->position);
        $this->assertFalse($pack->isRecommended);
        $this->assertNull($pack->description);
        // bonusEuros est calcule via bonusInEuros() quand l'API ne le fournit pas
        $this->assertSame(0.0, $pack->bonusInEuros());
    }

    #[Test]
    public function credit_pack_has_bonus_is_true_when_credits_exceed_amount(): void
    {
        $pack = CreditPack::fromArray($this->packPayload('pro'));

        $this->assertTrue($pack->hasBonus());
        $this->assertSame(10.0, $pack->bonusInEuros());
    }
}
