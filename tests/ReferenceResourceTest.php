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
use Scell\Sdk\DTOs\CountryReference;
use Scell\Sdk\Http\HttpClient;
use Scell\Sdk\Resources\ReferenceResource;

/**
 * Couvre la ReferenceResource (SDK 2.29.0).
 *
 * Endpoint backend public `GET /api/v1/reference/countries[/{code}]`.
 */
class ReferenceResourceTest extends TestCase
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

        $clientProp = new ReflectionProperty(HttpClient::class, 'client');
        $clientProp->setAccessible(true);
        $clientProp->setValue($http, new Client([
            'handler' => $stack,
            'http_errors' => false,
        ]));

        return $http;
    }

    #[Test]
    public function it_lists_countries(): void
    {
        $captured = [];
        $http = $this->buildHttp([
            new Response(200, [], (string) json_encode([
                'data' => [
                    [
                        'code' => 'FR', 'name' => 'France', 'known' => true, 'is_eu' => true, 'currency' => 'EUR',
                        'vat' => ['label' => 'TVA', 'example' => 'FR12345678901', 'regex' => '^FR[A-Z0-9]{2}\\d{9}$', 'vies_checkable' => true],
                        'national_id' => ['label' => 'SIREN / SIRET', 'scheme' => '0002', 'example' => '12345678901234', 'regex' => '^(\\d{9}|\\d{14})$', 'required_for_b2b' => true],
                        'legal_forms' => [['code' => 'SAS', 'label' => 'SAS']],
                    ],
                ],
                'meta' => ['count' => 1],
            ])),
        ], $captured);

        $resource = new ReferenceResource($http);
        $countries = $resource->countries();

        $this->assertCount(1, $countries);
        $this->assertInstanceOf(CountryReference::class, $countries[0]);
        $this->assertSame('FR', $countries[0]->code);
        $this->assertTrue($countries[0]->isEu);
        $this->assertSame('0002', $countries[0]->nationalId['scheme']);
        $this->assertSame('SAS', $countries[0]->legalForms[0]->code);
        $this->assertStringContainsString('reference/countries', (string) $captured[0]->getUri());
    }

    #[Test]
    public function it_fetches_a_single_country_uppercased(): void
    {
        $captured = [];
        $http = $this->buildHttp([
            new Response(200, [], (string) json_encode([
                'data' => [
                    'code' => 'DE', 'name' => 'Allemagne', 'known' => true, 'is_eu' => true, 'currency' => 'EUR',
                    'vat' => ['label' => 'USt-IdNr.', 'example' => 'DE123456789', 'regex' => '^DE\\d{9}$', 'vies_checkable' => true],
                    'national_id' => ['label' => 'Handelsregisternummer', 'scheme' => '0204', 'example' => 'HRB 123456', 'regex' => null, 'required_for_b2b' => false],
                    'legal_forms' => [['code' => 'GMBH', 'label' => 'GmbH']],
                ],
            ])),
        ], $captured);

        $resource = new ReferenceResource($http);
        $country = $resource->country('de');

        $this->assertSame('DE', $country->code);
        $this->assertNull($country->nationalId['regex']);
        $this->assertSame('GmbH', $country->legalForms[0]->label);
        $this->assertStringContainsString('reference/countries/DE', (string) $captured[0]->getUri());
    }
}
