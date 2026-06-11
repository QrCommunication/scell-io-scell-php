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
use Scell\Sdk\Config;
use Scell\Sdk\Http\HttpClient;
use Scell\Sdk\Resources\DocumentResource;
use Scell\Sdk\ScellApiClient;
use Scell\Sdk\ScellClient;

/**
 * Couvre `DocumentResource::preview()` (SDK 3.4.0) — endpoint backend
 * `POST /documents/preview` (apercu HTML non persiste, groupe api.flexible) —
 * et son wiring sur ScellClient / ScellApiClient.
 */
class DocumentResourceTest extends TestCase
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

    // -------------------------------------------------------------------------
    // preview()
    // -------------------------------------------------------------------------

    #[Test]
    public function preview_posts_payload_and_returns_raw_html(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'text/html'], '<!DOCTYPE html><html>apercu facture</html>')],
            $captured,
        );

        $payload = [
            'type' => 'invoice',
            'document_number' => 'FA-2026-0042',
            'buyer' => [
                'name' => 'Client SA',
                'siret' => '98765432109876',
                'is_individual' => false,
                'address' => [
                    'line1' => '1 rue de la Paix',
                    'postal_code' => '75002',
                    'city' => 'Paris',
                    'country' => 'FR',
                ],
            ],
            'lines' => [
                ['description' => 'Prestation', 'quantity' => 1, 'unit_price' => 1000.00, 'tax_rate' => 20.0],
            ],
            'issue_date' => '2026-06-11',
            'due_date' => '2026-07-11',
            'currency' => 'EUR',
            'notes' => 'Merci de votre confiance.',
            'payment_terms' => 'Paiement a 30 jours.',
        ];

        $html = (new DocumentResource($http))->preview($payload);

        $this->assertCount(1, $captured);
        $this->assertSame('POST', $captured[0]->getMethod());
        $this->assertSame(
            'https://api.scell.io/api/v1/documents/preview',
            (string) $captured[0]->getUri()->withQuery(''),
        );

        $body = json_decode((string) $captured[0]->getBody(), true);
        $this->assertSame('invoice', $body['type']);
        $this->assertSame('FA-2026-0042', $body['document_number']);
        $this->assertSame('Client SA', $body['buyer']['name']);
        $this->assertSame('Prestation', $body['lines'][0]['description']);
        $this->assertSame('2026-06-11', $body['issue_date']);

        $this->assertSame('<!DOCTYPE html><html>apercu facture</html>', $html);
    }

    #[Test]
    public function preview_supports_quote_type_with_minimal_payload(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'text/html'], '<html>apercu devis</html>')],
            $captured,
        );

        $html = (new DocumentResource($http))->preview(['type' => 'quote']);

        $body = json_decode((string) $captured[0]->getBody(), true);
        $this->assertSame(['type' => 'quote'], $body);
        $this->assertSame('<html>apercu devis</html>', $html);
    }

    // -------------------------------------------------------------------------
    // Wiring sur les clients
    // -------------------------------------------------------------------------

    #[Test]
    public function scell_api_client_exposes_documents_resource(): void
    {
        $client = ScellApiClient::withApiKey('sk_test_xxx', Config::local('http://localhost/api/v1'));

        $documents = $client->documents();

        $this->assertInstanceOf(DocumentResource::class, $documents);
        $this->assertSame($documents, $client->documents(), 'documents() should be cached (singleton par client)');
    }

    #[Test]
    public function scell_client_dashboard_exposes_documents_resource(): void
    {
        $client = new ScellClient('bearer-token-xxx', Config::local('http://localhost/api/v1'));

        $documents = $client->documents();

        $this->assertInstanceOf(DocumentResource::class, $documents);
        $this->assertSame($documents, $client->documents(), 'documents() should be cached (singleton par client)');
    }
}
