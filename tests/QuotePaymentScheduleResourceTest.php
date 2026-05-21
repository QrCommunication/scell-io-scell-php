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
use Scell\Sdk\DTOs\PaymentScheduleLine;
use Scell\Sdk\DTOs\PaymentSummary;
use Scell\Sdk\Http\HttpClient;
use Scell\Sdk\Resources\QuotePaymentScheduleResource;

/**
 * Couvre les nouvelles methodes SDK v2.13.0 pour l'echeancier de paiement.
 */
class QuotePaymentScheduleResourceTest extends TestCase
{
    private const QUOTE_ID = 'quote-uuid-42';
    private const LINE_ID = 'line-uuid-10';

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

    private function makeLinePayload(string $id = self::LINE_ID): array
    {
        return [
            'id' => $id,
            'quote_id' => self::QUOTE_ID,
            'order' => 1,
            'amount_type' => 'percent',
            'amount_value' => 30.0,
            'amount_ttc_snapshot' => null,
            'status' => 'pending',
            'auto_generate' => false,
            'due_date' => '2026-06-15',
            'milestone_label' => null,
            'description' => 'Acompte 30%',
            'invoice_id' => null,
            'invoiced_at' => null,
            'locked_at' => null,
            'reminder_tenant_sent_at' => null,
            'reminder_buyer_sent_at' => null,
            'overdue_alerted_at' => null,
            'created_at' => '2026-05-21T10:00:00Z',
            'updated_at' => '2026-05-21T10:00:00Z',
        ];
    }

    // -------------------------------------------------------------------------
    // list()
    // -------------------------------------------------------------------------

    #[Test]
    public function list_calls_get_endpoint_and_returns_dto_array(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => [$this->makeLinePayload()],
            ]))],
            $captured,
        );

        $lines = (new QuotePaymentScheduleResource($http))->list(self::QUOTE_ID);

        $this->assertCount(1, $captured);
        $this->assertSame('GET', $captured[0]->getMethod());
        $this->assertSame(
            'https://api.scell.io/api/v1/quotes/' . self::QUOTE_ID . '/payment-schedule',
            (string) $captured[0]->getUri()->withQuery(''),
        );

        $this->assertIsArray($lines);
        $this->assertCount(1, $lines);
        $this->assertInstanceOf(PaymentScheduleLine::class, $lines[0]);
        $this->assertSame(self::LINE_ID, $lines[0]->id);
        $this->assertSame('percent', $lines[0]->amountType);
        $this->assertSame(30.0, $lines[0]->amountValue);
    }

    // -------------------------------------------------------------------------
    // set()
    // -------------------------------------------------------------------------

    #[Test]
    public function set_calls_post_with_lines_and_returns_dto_array(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => [$this->makeLinePayload()],
            ]))],
            $captured,
        );

        $inputLines = [
            ['amount_type' => 'percent', 'amount_value' => 30, 'due_date' => '2026-06-15'],
        ];
        $lines = (new QuotePaymentScheduleResource($http))->set(self::QUOTE_ID, $inputLines);

        $this->assertSame('POST', $captured[0]->getMethod());
        $body = json_decode($captured[0]->getBody()->getContents(), true);
        $this->assertArrayHasKey('lines', $body);

        $this->assertInstanceOf(PaymentScheduleLine::class, $lines[0]);
    }

    // -------------------------------------------------------------------------
    // patch()
    // -------------------------------------------------------------------------

    #[Test]
    public function patch_calls_patch_with_changes_and_returns_dto_array(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => [$this->makeLinePayload()],
            ]))],
            $captured,
        );

        $changes = ['add' => [['amount_type' => 'percent', 'amount_value' => 70, 'milestone_label' => 'Livraison']]];
        $lines = (new QuotePaymentScheduleResource($http))->patch(self::QUOTE_ID, $changes);

        $this->assertSame('PATCH', $captured[0]->getMethod());
        $this->assertSame(
            'https://api.scell.io/api/v1/quotes/' . self::QUOTE_ID . '/payment-schedule',
            (string) $captured[0]->getUri()->withQuery(''),
        );

        $this->assertInstanceOf(PaymentScheduleLine::class, $lines[0]);
    }

    // -------------------------------------------------------------------------
    // delete()
    // -------------------------------------------------------------------------

    #[Test]
    public function delete_calls_delete_endpoint_and_returns_void(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(204)],
            $captured,
        );

        $result = (new QuotePaymentScheduleResource($http))->delete(self::QUOTE_ID);

        $this->assertSame('DELETE', $captured[0]->getMethod());
        $this->assertSame(
            'https://api.scell.io/api/v1/quotes/' . self::QUOTE_ID . '/payment-schedule',
            (string) $captured[0]->getUri()->withQuery(''),
        );
        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // summary()
    // -------------------------------------------------------------------------

    #[Test]
    public function summary_calls_get_payment_summary_and_returns_dto(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => [
                    'quote_id' => self::QUOTE_ID,
                    'total_ttc' => 1200.0,
                    'total_invoiced' => 360.0,
                    'total_credited' => 0.0,
                    'net_invoiced' => 360.0,
                    'remaining' => 840.0,
                    'percent_invoiced' => 30.0,
                    'lines_total' => 2,
                    'lines_invoiced' => 1,
                    'lines_pending' => 1,
                    'balance_invoice_emitted' => false,
                ],
            ]))],
            $captured,
        );

        $summary = (new QuotePaymentScheduleResource($http))->summary(self::QUOTE_ID);

        $this->assertSame('GET', $captured[0]->getMethod());
        $this->assertStringContainsString('payment-summary', (string) $captured[0]->getUri());

        $this->assertInstanceOf(PaymentSummary::class, $summary);
        $this->assertSame(self::QUOTE_ID, $summary->quoteId);
        $this->assertSame(1200.0, $summary->totalTtc);
        $this->assertSame(840.0, $summary->remaining);
        $this->assertSame(30.0, $summary->percentInvoiced);
        $this->assertFalse($summary->isComplete());
    }

    // -------------------------------------------------------------------------
    // convertLine()
    // -------------------------------------------------------------------------

    #[Test]
    public function convert_line_calls_post_and_returns_invoice_dto(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(201, ['Content-Type' => 'application/json'], json_encode([
                'data' => [
                    'id' => 'invoice-uuid-99',
                    'invoice_number' => 'FA-2026-0001',
                    'status' => 'draft',
                    'direction' => 'outgoing',
                    'output_format' => 'facturx',
                    'issue_date' => '2026-06-15',
                    'total_ht' => 300.0,
                    'total_tax' => 60.0,
                    'total_ttc' => 360.0,
                    'environment' => 'sandbox',
                    'invoice_type' => 'deposit',
                    'seller' => [
                        'name' => 'Ma Societe',
                        'siret' => '12345678901234',
                        'address' => ['line1' => '1 rue Test', 'postal_code' => '75001', 'city' => 'Paris', 'country' => 'FR'],
                    ],
                    'buyer' => [
                        'name' => 'Client SA',
                        'siret' => '98765432109876',
                        'address' => ['line1' => '2 av Demo', 'postal_code' => '69000', 'city' => 'Lyon', 'country' => 'FR'],
                    ],
                    'lines' => [],
                    'created_at' => '2026-06-15T10:00:00Z',
                ],
            ]))],
            $captured,
        );

        $invoice = (new QuotePaymentScheduleResource($http))->convertLine(
            self::QUOTE_ID,
            self::LINE_ID,
            ['issue_date' => '2026-06-15'],
        );

        $this->assertSame('POST', $captured[0]->getMethod());
        $this->assertStringContainsString(
            'payment-schedule/lines/' . self::LINE_ID . '/convert',
            (string) $captured[0]->getUri(),
        );

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertSame('invoice-uuid-99', $invoice->id);
    }

    // -------------------------------------------------------------------------
    // presets()
    // -------------------------------------------------------------------------

    #[Test]
    public function presets_calls_get_payment_schedule_presets_and_returns_array(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => [
                    ['name' => '30/70', 'description' => 'Acompte 30% + solde 70%', 'lines' => []],
                    ['name' => '3x mensuel', 'description' => 'Trois versements egaux', 'lines' => []],
                ],
            ]))],
            $captured,
        );

        $presets = (new QuotePaymentScheduleResource($http))->presets();

        $this->assertSame('GET', $captured[0]->getMethod());
        $this->assertSame(
            'https://api.scell.io/api/v1/payment-schedule-presets',
            (string) $captured[0]->getUri()->withQuery(''),
        );

        $this->assertIsArray($presets);
        $this->assertCount(2, $presets);
        $this->assertSame('30/70', $presets[0]['name']);
    }

    // -------------------------------------------------------------------------
    // PaymentScheduleLine DTO helpers
    // -------------------------------------------------------------------------

    #[Test]
    public function payment_schedule_line_dto_helpers_work_correctly(): void
    {
        $pending = PaymentScheduleLine::fromArray($this->makeLinePayload());
        $this->assertTrue($pending->isPending());
        $this->assertFalse($pending->isInvoiced());
        $this->assertFalse($pending->isCancelled());
        $this->assertFalse($pending->isLocked());

        $invoiced = PaymentScheduleLine::fromArray(array_merge($this->makeLinePayload(), [
            'status' => 'invoiced',
            'invoice_id' => 'inv-001',
            'invoiced_at' => '2026-06-15T10:00:00Z',
            'locked_at' => '2026-06-01T00:00:00Z',
        ]));
        $this->assertTrue($invoiced->isInvoiced());
        $this->assertTrue($invoiced->isLocked());
    }
}
