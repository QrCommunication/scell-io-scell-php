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
use Scell\Sdk\DTOs\Invoice;
use Scell\Sdk\Enums\DisputeType;
use Scell\Sdk\Http\HttpClient;
use Scell\Sdk\Resources\TenantIncomingInvoiceResource;

/**
 * Couvre la nouvelle methode `TenantIncomingInvoiceResource::dispute()`
 * (SDK 2.24.0) — endpoint backend `POST /tenant/invoices/incoming/{id}/dispute`.
 */
class TenantIncomingInvoiceDisputeTest extends TestCase
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
    private function disputedInvoicePayload(): array
    {
        return [
            'id' => 'invoice-uuid-99',
            'invoice_number' => 'FOURN-2026-001',
            'direction' => 'incoming',
            'output_format' => 'facturx',
            'issue_date' => '2026-01-26',
            'total_ht' => 1000.00,
            'total_tax' => 200.00,
            'total_ttc' => 1200.00,
            'seller' => [
                'name' => 'Fournisseur SA',
                'siret' => '11111111111111',
                'address' => ['street' => '5 rue Test', 'postal_code' => '69001', 'city' => 'Lyon', 'country' => 'FR'],
            ],
            'buyer' => [
                'name' => 'Mon Client SARL',
                'siret' => '22222222222222',
                'address' => ['street' => '1 rue Test', 'postal_code' => '75001', 'city' => 'Paris', 'country' => 'FR'],
            ],
            'lines' => [],
            'status' => 'disputed',
            'environment' => 'sandbox',
        ];
    }

    #[Test]
    public function dispute_calls_post_endpoint_with_reason_only(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => $this->disputedInvoicePayload(),
            ]))],
            $captured,
        );

        $invoice = (new TenantIncomingInvoiceResource($http))->dispute(
            'invoice-uuid-99',
            'Montant facture incorrect',
        );

        $this->assertCount(1, $captured);
        $this->assertSame('POST', $captured[0]->getMethod());
        $this->assertSame(
            'https://api.scell.io/api/v1/tenant/invoices/incoming/invoice-uuid-99/dispute',
            (string) $captured[0]->getUri()->withQuery(''),
        );

        $body = json_decode((string) $captured[0]->getBody(), true);
        $this->assertSame(['reason' => 'Montant facture incorrect'], $body);

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertSame('invoice-uuid-99', $invoice->id);
    }

    #[Test]
    public function dispute_passes_dispute_type_enum_as_string(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => $this->disputedInvoicePayload(),
            ]))],
            $captured,
        );

        (new TenantIncomingInvoiceResource($http))->dispute(
            'invoice-uuid-99',
            'Litige sur la qualite des fournitures',
            DisputeType::QualityDispute,
        );

        $body = json_decode((string) $captured[0]->getBody(), true);
        $this->assertSame('Litige sur la qualite des fournitures', $body['reason']);
        $this->assertSame('quality_dispute', $body['dispute_type']);
    }

    #[Test]
    public function dispute_accepts_dispute_type_as_raw_string(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => $this->disputedInvoicePayload(),
            ]))],
            $captured,
        );

        (new TenantIncomingInvoiceResource($http))->dispute(
            'invoice-uuid-99',
            'Custom dispute type forward',
            'custom_string',
        );

        $body = json_decode((string) $captured[0]->getBody(), true);
        $this->assertSame('custom_string', $body['dispute_type']);
    }

    #[Test]
    public function dispute_passes_expected_amount_when_provided(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => $this->disputedInvoicePayload(),
            ]))],
            $captured,
        );

        (new TenantIncomingInvoiceResource($http))->dispute(
            'invoice-uuid-99',
            'Facture 1500 mais devis a 1200',
            DisputeType::AmountDispute,
            1200.00,
        );

        $body = json_decode((string) $captured[0]->getBody(), true);
        $this->assertSame('amount_dispute', $body['dispute_type']);
        // json_decode peut typer entier ou float selon la valeur — on cast pour comparer
        $this->assertEquals(1200.00, $body['expected_amount']);
    }

    #[Test]
    public function dispute_omits_expected_amount_when_null(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => $this->disputedInvoicePayload(),
            ]))],
            $captured,
        );

        (new TenantIncomingInvoiceResource($http))->dispute(
            'invoice-uuid-99',
            'Generic reason',
            DisputeType::Other,
        );

        $body = json_decode((string) $captured[0]->getBody(), true);
        $this->assertArrayNotHasKey('expected_amount', $body);
    }
}
