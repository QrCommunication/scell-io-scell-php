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
use Scell\Sdk\DTOs\CreditNote;
use Scell\Sdk\DTOs\Invoice;
use Scell\Sdk\Enums\PaymentMeansCode;
use Scell\Sdk\Exceptions\ValidationException;
use Scell\Sdk\Http\HttpClient;
use Scell\Sdk\Resources\InvoiceResource;
use Scell\Sdk\Resources\TenantIncomingInvoiceResource;

/**
 * Couvre BT-81 / `payment_means_code` requis sur les endpoints `mark-paid`
 * (SDK 2.25.0 / API 2026-05-28).
 *
 * Endpoints concernes :
 *   - POST /v1/invoices/{id}/mark-paid (sortantes + incoming via direction)
 *   - POST /v1/tenant/invoices/incoming/{id}/mark-paid
 *
 * NOTE : l'endpoint Sanctum SPA `POST /v1/invoices/incoming/{id}/mark-paid`
 * (dashboard) n'est pas accessible via SDK (clefs sk_/pk_), donc absent de
 * cette suite.
 */
class MarkPaidPaymentMeansCodeTest extends TestCase
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
    private function paidInvoicePayload(): array
    {
        return [
            'id' => 'invoice-uuid-77',
            'invoice_number' => 'FACT-2026-0077',
            'direction' => 'outgoing',
            'output_format' => 'facturx',
            'issue_date' => '2026-05-20',
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
            'lines' => [],
            'status' => 'completed',
            'environment' => 'sandbox',
            'paid_at' => '2026-05-28T10:00:00+00:00',
            'payment_means_code' => '58',
            'payment_means_text' => 'Compte BNP ...4567',
            'payment_reference' => 'VIR-2026-001234',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paidIncomingInvoicePayload(): array
    {
        $payload = $this->paidInvoicePayload();
        $payload['id'] = 'incoming-uuid-88';
        $payload['invoice_number'] = 'FOURN-2026-088';
        $payload['direction'] = 'incoming';
        $payload['status'] = 'paid';
        $payload['payment_means_code'] = '30';
        $payload['payment_means_text'] = null;
        return $payload;
    }

    // =====================================================================
    // InvoiceResource::markPaid()
    // =====================================================================

    #[Test]
    public function invoice_mark_paid_sends_payment_means_code_when_enum_passed(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => $this->paidInvoicePayload(),
            ]))],
            $captured,
        );

        $invoice = (new InvoiceResource($http))->markPaid(
            'invoice-uuid-77',
            PaymentMeansCode::SEPA_CREDIT_TRANSFER,
            [
                'payment_reference' => 'VIR-2026-001234',
                'payment_means_text' => 'Compte BNP ...4567',
                'paid_at' => '2026-05-28',
            ],
        );

        $this->assertCount(1, $captured);
        $this->assertSame('POST', $captured[0]->getMethod());
        $this->assertSame(
            'https://api.scell.io/api/v1/invoices/invoice-uuid-77/mark-paid',
            (string) $captured[0]->getUri()->withQuery(''),
        );

        $body = json_decode((string) $captured[0]->getBody(), true);
        $this->assertSame('58', $body['payment_means_code']);
        $this->assertSame('Compte BNP ...4567', $body['payment_means_text']);
        $this->assertSame('VIR-2026-001234', $body['payment_reference']);
        $this->assertSame('2026-05-28', $body['paid_at']);

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertSame(PaymentMeansCode::SEPA_CREDIT_TRANSFER, $invoice->paymentMeansCode);
        $this->assertSame('Compte BNP ...4567', $invoice->paymentMeansText);
    }

    #[Test]
    public function invoice_mark_paid_accepts_raw_string_for_payment_means_code(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => $this->paidInvoicePayload(),
            ]))],
            $captured,
        );

        (new InvoiceResource($http))->markPaid('invoice-uuid-77', '58');

        $body = json_decode((string) $captured[0]->getBody(), true);
        $this->assertSame('58', $body['payment_means_code']);
        // Pas d'autres champs envoyes par defaut
        $this->assertArrayNotHasKey('payment_means_text', $body);
        $this->assertArrayNotHasKey('payment_reference', $body);
        $this->assertArrayNotHasKey('paid_at', $body);
    }

    #[Test]
    public function invoice_mark_paid_propagates_422_when_backend_rejects_missing_or_invalid_code(): void
    {
        // Simule le 422 ValidationException leve cote backend si on tente d'envoyer
        // un payment_means_code invalide. Cas qui valide le contrat (backend gate).
        $captured = [];
        $http = $this->buildHttp(
            [new Response(422, ['Content-Type' => 'application/json'], json_encode([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'payment_means_code' => [
                        'Le mode d\'encaissement est obligatoire (conformité Factur-X BT-81).',
                    ],
                ],
            ]))],
            $captured,
        );

        $this->expectException(ValidationException::class);

        // Une valeur arbitraire string passe le type-check PHP mais sera rejetee
        // par le backend si elle ne fait pas partie de l'enum UN/ECE 4461.
        (new InvoiceResource($http))->markPaid('invoice-uuid-77', 'invalid_code');
    }

    // =====================================================================
    // TenantIncomingInvoiceResource::markPaid()
    // =====================================================================

    #[Test]
    public function tenant_incoming_mark_paid_sends_payment_means_code_with_all_optional_fields(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => $this->paidIncomingInvoicePayload(),
            ]))],
            $captured,
        );

        $invoice = (new TenantIncomingInvoiceResource($http))->markPaid(
            'incoming-uuid-88',
            PaymentMeansCode::CREDIT_TRANSFER,
            [
                'payment_reference' => 'VIR-INT-2026-001',
                'payment_means_text' => 'Compte Credit Mutuel ...4321',
                'paid_at' => '2026-01-28',
                'note' => 'Paiement par virement international',
            ],
        );

        $this->assertCount(1, $captured);
        $this->assertSame('POST', $captured[0]->getMethod());
        $this->assertSame(
            'https://api.scell.io/api/v1/tenant/invoices/incoming/incoming-uuid-88/mark-paid',
            (string) $captured[0]->getUri()->withQuery(''),
        );

        $body = json_decode((string) $captured[0]->getBody(), true);
        $this->assertSame('30', $body['payment_means_code']);
        $this->assertSame('VIR-INT-2026-001', $body['payment_reference']);
        $this->assertSame('Compte Credit Mutuel ...4321', $body['payment_means_text']);
        $this->assertSame('2026-01-28', $body['paid_at']);
        $this->assertSame('Paiement par virement international', $body['note']);

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertSame(PaymentMeansCode::CREDIT_TRANSFER, $invoice->paymentMeansCode);
        $this->assertNull($invoice->paymentMeansText);
    }

    #[Test]
    public function tenant_incoming_mark_paid_accepts_raw_string_code(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => $this->paidIncomingInvoicePayload(),
            ]))],
            $captured,
        );

        (new TenantIncomingInvoiceResource($http))->markPaid('incoming-uuid-88', '20');

        $body = json_decode((string) $captured[0]->getBody(), true);
        $this->assertSame('20', $body['payment_means_code']);
        $this->assertArrayNotHasKey('payment_reference', $body);
    }

    #[Test]
    public function tenant_incoming_mark_paid_propagates_422_validation_error(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(422, ['Content-Type' => 'application/json'], json_encode([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'payment_means_code' => [
                        'Le mode d\'encaissement doit etre un code UN/ECE 4461 valide.',
                    ],
                ],
            ]))],
            $captured,
        );

        $this->expectException(ValidationException::class);

        (new TenantIncomingInvoiceResource($http))->markPaid('incoming-uuid-88', '999');
    }

    // =====================================================================
    // DTOs : persistence des champs
    // =====================================================================

    #[Test]
    public function invoice_dto_maps_payment_means_code_from_api_response(): void
    {
        $payload = $this->paidInvoicePayload();
        $payload['payment_means_code'] = '48';
        $payload['payment_means_text'] = 'Carte Visa ...1234';

        $invoice = Invoice::fromArray($payload);

        $this->assertSame(PaymentMeansCode::BANK_CARD, $invoice->paymentMeansCode);
        $this->assertSame('Carte Visa ...1234', $invoice->paymentMeansText);
    }

    #[Test]
    public function invoice_dto_defaults_to_null_when_fields_absent(): void
    {
        // Retrocompat : backend ancien qui ne renvoie pas encore BT-81
        $payload = $this->paidInvoicePayload();
        unset($payload['payment_means_code'], $payload['payment_means_text']);

        $invoice = Invoice::fromArray($payload);

        $this->assertNull($invoice->paymentMeansCode);
        $this->assertNull($invoice->paymentMeansText);
    }

    #[Test]
    public function invoice_dto_handles_unknown_payment_means_code_gracefully(): void
    {
        // Backend introduit un nouveau code => SDK fallback null pour ne pas
        // casser le consommateur (PaymentMeansCode::tryFrom)
        $payload = $this->paidInvoicePayload();
        $payload['payment_means_code'] = '999';

        $invoice = Invoice::fromArray($payload);

        $this->assertNull($invoice->paymentMeansCode);
    }

    #[Test]
    public function credit_note_dto_maps_payment_means_code_and_text(): void
    {
        $payload = [
            'id' => 'cn-uuid-1',
            'credit_note_number' => 'AVOIR-2026-001',
            'invoice_id' => 'invoice-uuid-1',
            'type' => 'partial',
            'reason' => 'Remboursement partiel',
            'status' => 'validated',
            'total_ht' => 100.00,
            'total_tax' => 20.00,
            'total_ttc' => 120.00,
            'lines' => [],
            'environment' => 'sandbox',
            'payment_means_code' => '59',
            'payment_means_text' => 'Mandat SEPA REF-12345',
        ];

        $cn = CreditNote::fromArray($payload);

        $this->assertSame(PaymentMeansCode::SEPA_DIRECT_DEBIT, $cn->paymentMeansCode);
        $this->assertSame('Mandat SEPA REF-12345', $cn->paymentMeansText);
    }

    #[Test]
    public function credit_note_dto_defaults_payment_means_to_null_when_absent(): void
    {
        $payload = [
            'id' => 'cn-uuid-2',
            'credit_note_number' => 'AVOIR-2026-002',
            'invoice_id' => 'invoice-uuid-2',
            'type' => 'full',
            'reason' => 'Annulation',
            'status' => 'draft',
            'total_ht' => 0.00,
            'total_tax' => 0.00,
            'total_ttc' => 0.00,
            'lines' => [],
            'environment' => 'sandbox',
        ];

        $cn = CreditNote::fromArray($payload);

        $this->assertNull($cn->paymentMeansCode);
        $this->assertNull($cn->paymentMeansText);
    }

    // =====================================================================
    // Enum PaymentMeansCode
    // =====================================================================

    #[Test]
    public function payment_means_code_enum_exposes_fr_and_en_labels(): void
    {
        $this->assertSame('Virement SEPA', PaymentMeansCode::SEPA_CREDIT_TRANSFER->label());
        $this->assertSame('SEPA credit transfer', PaymentMeansCode::SEPA_CREDIT_TRANSFER->labelEn());

        $this->assertSame('Cheque', PaymentMeansCode::CHEQUE->label());
        $this->assertSame('Cheque', PaymentMeansCode::CHEQUE->labelEn());

        $this->assertSame('Especes', PaymentMeansCode::IN_CASH->label());
        $this->assertSame('Cash', PaymentMeansCode::IN_CASH->labelEn());

        // Tous les cases ont un label non vide en FR + EN
        foreach (PaymentMeansCode::cases() as $code) {
            $this->assertNotEmpty($code->label());
            $this->assertNotEmpty($code->labelEn());
        }
    }

    #[Test]
    public function payment_means_code_common_b2b_france_returns_priority_order(): void
    {
        $codes = PaymentMeansCode::commonB2bFrance();

        $this->assertCount(6, $codes);
        $this->assertSame(PaymentMeansCode::SEPA_CREDIT_TRANSFER, $codes[0]);
        $this->assertSame(PaymentMeansCode::CREDIT_TRANSFER, $codes[1]);
        $this->assertSame(PaymentMeansCode::CHEQUE, $codes[2]);
        $this->assertSame(PaymentMeansCode::BANK_CARD, $codes[3]);
        $this->assertSame(PaymentMeansCode::SEPA_DIRECT_DEBIT, $codes[4]);
        $this->assertSame(PaymentMeansCode::IN_CASH, $codes[5]);
    }

    #[Test]
    public function payment_means_code_has_11_un_ece_4461_cases(): void
    {
        $values = array_map(fn(PaymentMeansCode $c) => $c->value, PaymentMeansCode::cases());

        // Sous-ensemble valide cote backend (cf. App\Enums\Invoice\PaymentMeansCode)
        $this->assertSame(
            ['1', '10', '20', '30', '42', '48', '49', '57', '58', '59', '97'],
            $values,
        );
    }
}
