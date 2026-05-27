<?php

declare(strict_types=1);

namespace Scell\Sdk\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Scell\Sdk\DTOs\Address;
use Scell\Sdk\Http\HttpClient;
use Scell\Sdk\Resources\InvoiceResource;

/**
 * Couvre `parent_quote_id` sur POST /api/v1/invoices (SDK 2.19.0).
 *
 * Le builder Invoice expose deja la methode fluent `parentQuoteId()` depuis
 * SDK 2.15.0, mais l'API backend accepte desormais ce champ aussi sur les
 * factures standard (pas seulement via convertToDeposit/convertToBalance).
 *
 * Verifications :
 *  - Le builder->parentQuoteId() serialise bien `parent_quote_id` dans le payload
 *    final POST /api/v1/invoices (regression : normalizeCreatePayload droppait
 *    silencieusement le champ avant 2.19.0).
 *  - Le DTO Invoice expose `parentQuoteId` en lecture (fromArray).
 *  - Retrocompat : absence d'appel = payload inchange (pas de cle parent_quote_id).
 */
class InvoiceParentQuoteIdTest extends TestCase
{
    /**
     * @param Response[] $responses
     * @param Request[]  $captured Reference rempli par le test.
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
    private function invoiceFixture(?string $parentQuoteId = null): array
    {
        return [
            'data' => [
                'id' => '019d0000-0000-7000-8000-000000000001',
                'invoice_number' => 'FACT-2026-0001',
                'direction' => 'outgoing',
                'output_format' => 'facturx',
                'issue_date' => '2026-05-27',
                'total_ht' => 1000.00,
                'total_tax' => 200.00,
                'total_ttc' => 1200.00,
                'seller' => [
                    'name' => 'Acme Corp',
                    'siret' => '12345678901234',
                    'address' => [
                        'street' => '1 rue Test',
                        'postal_code' => '75001',
                        'city' => 'Paris',
                        'country' => 'FR',
                    ],
                ],
                'buyer' => [
                    'name' => 'Client SARL',
                    'siret' => '98765432101234',
                    'address' => [
                        'street' => '2 avenue Demo',
                        'postal_code' => '69001',
                        'city' => 'Lyon',
                        'country' => 'FR',
                    ],
                ],
                'lines' => [
                    [
                        'description' => 'Service',
                        'quantity' => 1.0,
                        'unit_price' => 1000.00,
                        'tax_rate' => 20.0,
                        'total_ht' => 1000.00,
                        'total_tax' => 200.00,
                        'total_ttc' => 1200.00,
                    ],
                ],
                'status' => 'draft',
                'environment' => 'sandbox',
                'parent_quote_id' => $parentQuoteId,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lastRequestBody(array $captured): array
    {
        $request = end($captured);
        return json_decode((string) $request->getBody(), true);
    }

    #[Test]
    public function builder_serializes_parent_quote_id_on_standard_invoice(): void
    {
        $quoteId = '019d1234-5678-7000-8000-abcdef012345';
        $captured = [];
        $http = $this->buildHttp(
            [new Response(201, ['Content-Type' => 'application/json'], json_encode($this->invoiceFixture($quoteId)))],
            $captured,
        );
        $resource = new InvoiceResource($http);

        $resource->builder()
            ->outgoing()
            ->facturX()
            ->issueDate('2026-05-27')
            ->seller(
                '12345678901234',
                'Acme Corp',
                new Address(line1: '1 rue Test', postalCode: '75001', city: 'Paris', country: 'FR')
            )
            ->buyer(
                '98765432101234',
                'Client SARL',
                new Address(line1: '2 avenue Demo', postalCode: '69001', city: 'Lyon', country: 'FR')
            )
            ->addLine('Service', 1.0, 1000.00, 20.0)
            ->parentQuoteId($quoteId)
            ->create();

        $body = $this->lastRequestBody($captured);

        $this->assertArrayHasKey('parent_quote_id', $body);
        $this->assertSame($quoteId, $body['parent_quote_id']);
    }

    #[Test]
    public function payload_omits_parent_quote_id_when_not_set_backward_compat(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(201, ['Content-Type' => 'application/json'], json_encode($this->invoiceFixture()))],
            $captured,
        );
        $resource = new InvoiceResource($http);

        $resource->builder()
            ->outgoing()
            ->facturX()
            ->issueDate('2026-05-27')
            ->seller(
                '12345678901234',
                'Acme Corp',
                new Address(line1: '1 rue Test', postalCode: '75001', city: 'Paris', country: 'FR')
            )
            ->buyer(
                '98765432101234',
                'Client SARL',
                new Address(line1: '2 avenue Demo', postalCode: '69001', city: 'Lyon', country: 'FR')
            )
            ->addLine('Service', 1.0, 1000.00, 20.0)
            ->create();

        $body = $this->lastRequestBody($captured);

        $this->assertArrayNotHasKey('parent_quote_id', $body);
    }

    #[Test]
    public function dto_exposes_parent_quote_id_from_api_response(): void
    {
        $quoteId = '019d1234-5678-7000-8000-abcdef012345';
        $captured = [];
        $http = $this->buildHttp(
            [new Response(201, ['Content-Type' => 'application/json'], json_encode($this->invoiceFixture($quoteId)))],
            $captured,
        );
        $resource = new InvoiceResource($http);

        $invoice = $resource->builder()
            ->outgoing()
            ->facturX()
            ->issueDate('2026-05-27')
            ->seller(
                '12345678901234',
                'Acme Corp',
                new Address(line1: '1 rue Test', postalCode: '75001', city: 'Paris', country: 'FR')
            )
            ->buyer(
                '98765432101234',
                'Client SARL',
                new Address(line1: '2 avenue Demo', postalCode: '69001', city: 'Lyon', country: 'FR')
            )
            ->addLine('Service', 1.0, 1000.00, 20.0)
            ->parentQuoteId($quoteId)
            ->create();

        $this->assertSame($quoteId, $invoice->parentQuoteId);
    }
}
