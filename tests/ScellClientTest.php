<?php

declare(strict_types=1);

namespace Scell\Sdk\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Scell\Sdk\Config;
use Scell\Sdk\DTOs\Address;
use Scell\Sdk\DTOs\Balance;
use Scell\Sdk\DTOs\Company;
use Scell\Sdk\DTOs\Invoice;
use Scell\Sdk\DTOs\InvoiceLine;
use Scell\Sdk\DTOs\Signature;
use Scell\Sdk\DTOs\Signer;
use Scell\Sdk\Enums\AuthMethod;
use Scell\Sdk\Enums\Direction;
use Scell\Sdk\Enums\Environment;
use Scell\Sdk\Enums\InvoiceStatus;
use Scell\Sdk\Enums\OutputFormat;
use Scell\Sdk\Enums\SignatureStatus;
use Scell\Sdk\Enums\WebhookEvent;
use Scell\Sdk\Exceptions\AuthenticationException;
use Scell\Sdk\Exceptions\RateLimitException;
use Scell\Sdk\Exceptions\ValidationException;
use Scell\Sdk\Webhooks\WebhookVerifier;

class ScellClientTest extends TestCase
{
    #[Test]
    public function it_creates_address_from_array(): void
    {
        $address = Address::fromArray([
            'line1' => '1 Rue de la Paix',
            'line2' => 'Batiment A',
            'postal_code' => '75001',
            'city' => 'Paris',
            'country' => 'FR',
        ]);

        $this->assertEquals('1 Rue de la Paix', $address->line1);
        $this->assertEquals('Batiment A', $address->line2);
        $this->assertEquals('75001', $address->postalCode);
        $this->assertEquals('Paris', $address->city);
        $this->assertEquals('FR', $address->country);
    }

    #[Test]
    public function it_formats_address(): void
    {
        $address = new Address(
            line1: '1 Rue de la Paix',
            postalCode: '75001',
            city: 'Paris',
        );

        $formatted = $address->formatted();
        $this->assertStringContainsString('1 Rue de la Paix', $formatted);
        $this->assertStringContainsString('75001 Paris', $formatted);
    }

    #[Test]
    public function it_creates_invoice_line_with_auto_calculation(): void
    {
        $line = InvoiceLine::create(
            description: 'Prestation de service',
            quantity: 2,
            unitPrice: 100.00,
            taxRate: 20.0
        );

        $this->assertEquals(200.00, $line->totalHt);
        $this->assertEquals(40.00, $line->totalTax);
        $this->assertEquals(240.00, $line->totalTtc);
    }

    #[Test]
    public function it_creates_signer(): void
    {
        $signer = Signer::create(
            firstName: 'Jean',
            lastName: 'Dupont',
            authMethod: AuthMethod::Email,
            email: 'jean@example.com'
        );

        $this->assertEquals('Jean', $signer->firstName);
        $this->assertEquals('Dupont', $signer->lastName);
        $this->assertEquals(AuthMethod::Email, $signer->authMethod);
        $this->assertEquals('jean@example.com', $signer->email);
        $this->assertEquals('Jean Dupont', $signer->fullName());
    }

    #[Test]
    public function it_converts_signer_to_array(): void
    {
        $signer = Signer::create(
            firstName: 'Jean',
            lastName: 'Dupont',
            authMethod: AuthMethod::Sms,
            phone: '+33612345678'
        );

        $array = $signer->toArray();

        $this->assertEquals('Jean', $array['first_name']);
        $this->assertEquals('Dupont', $array['last_name']);
        $this->assertEquals('sms', $array['auth_method']);
        $this->assertEquals('+33612345678', $array['phone']);
    }

    #[Test]
    public function it_parses_invoice_from_api_response(): void
    {
        $data = [
            'id' => '123e4567-e89b-12d3-a456-426614174000',
            'invoice_number' => 'FACT-2024-001',
            'direction' => 'outgoing',
            'output_format' => 'facturx',
            'issue_date' => '2024-01-15',
            'total_ht' => 1000.00,
            'total_tax' => 200.00,
            'total_ttc' => 1200.00,
            'seller' => [
                'siret' => '12345678901234',
                'name' => 'Ma Societe',
                'address' => [
                    'line1' => '1 Rue Test',
                    'postal_code' => '75001',
                    'city' => 'Paris',
                ],
            ],
            'buyer' => [
                'siret' => '98765432109876',
                'name' => 'Client SA',
                'address' => [
                    'line1' => '2 Avenue Client',
                    'postal_code' => '69001',
                    'city' => 'Lyon',
                ],
            ],
            'lines' => [
                [
                    'description' => 'Prestation',
                    'quantity' => 1,
                    'unit_price' => 1000.00,
                    'tax_rate' => 20.0,
                    'total_ht' => 1000.00,
                    'total_tax' => 200.00,
                    'total_ttc' => 1200.00,
                ],
            ],
            'status' => 'validated',
            'environment' => 'production',
        ];

        $invoice = Invoice::fromArray($data);

        $this->assertEquals('123e4567-e89b-12d3-a456-426614174000', $invoice->id);
        $this->assertEquals('FACT-2024-001', $invoice->invoiceNumber);
        $this->assertEquals(Direction::Outgoing, $invoice->direction);
        $this->assertEquals(OutputFormat::FacturX, $invoice->outputFormat);
        $this->assertEquals(InvoiceStatus::Validated, $invoice->status);
        $this->assertEquals(Environment::Production, $invoice->environment);
        $this->assertCount(1, $invoice->lines);
        $this->assertTrue($invoice->isOutgoing());
        $this->assertFalse($invoice->isSandbox());
    }

    #[Test]
    public function it_parses_signature_from_api_response(): void
    {
        $data = [
            'id' => '123e4567-e89b-12d3-a456-426614174001',
            'title' => 'Contrat de prestation',
            'document_name' => 'contrat.pdf',
            'document_size' => 12345,
            'signers' => [
                [
                    'id' => 'signer-1',
                    'first_name' => 'Jean',
                    'last_name' => 'Dupont',
                    'email' => 'jean@example.com',
                    'auth_method' => 'email',
                    'status' => 'signed',
                    'signed_at' => '2024-01-15T10:30:00Z',
                ],
            ],
            'status' => 'completed',
            'environment' => 'sandbox',
        ];

        $signature = Signature::fromArray($data);

        $this->assertEquals('Contrat de prestation', $signature->title);
        $this->assertEquals(SignatureStatus::Completed, $signature->status);
        $this->assertTrue($signature->isSandbox());
        $this->assertTrue($signature->isCompleted());
        $this->assertCount(1, $signature->signers);
        $this->assertTrue($signature->signers[0]->hasSigned());
    }

    #[Test]
    public function it_calculates_signature_progress(): void
    {
        $data = [
            'id' => 'sig-1',
            'title' => 'Test',
            'document_name' => 'test.pdf',
            'document_size' => 100,
            'signers' => [
                ['id' => '1', 'first_name' => 'A', 'last_name' => 'A', 'auth_method' => 'email', 'status' => 'signed', 'signed_at' => '2024-01-01'],
                ['id' => '2', 'first_name' => 'B', 'last_name' => 'B', 'auth_method' => 'email', 'status' => 'pending'],
            ],
            'status' => 'partially_signed',
            'environment' => 'production',
        ];

        $signature = Signature::fromArray($data);

        $this->assertEquals(50, $signature->progress());
        $this->assertEquals(1, $signature->signedCount());
        $this->assertEquals(1, $signature->pendingSignersCount());
    }

    #[Test]
    public function it_parses_balance_from_api_response(): void
    {
        $data = [
            'amount' => 150.50,
            'currency' => 'EUR',
            'auto_reload_enabled' => true,
            'auto_reload_threshold' => 20.00,
            'auto_reload_amount' => 100.00,
            'low_balance_alert_threshold' => 10.00,
            'critical_balance_alert_threshold' => 5.00,
        ];

        $balance = Balance::fromArray($data);

        $this->assertEquals(150.50, $balance->amount);
        $this->assertEquals('EUR', $balance->currency);
        $this->assertTrue($balance->autoReloadEnabled);
        $this->assertFalse($balance->isLow());
        $this->assertFalse($balance->isCritical());
        $this->assertTrue($balance->canAfford(100.00));
        $this->assertFalse($balance->canAfford(200.00));
    }

    #[Test]
    public function it_verifies_webhook_signature(): void
    {
        $secret = 'whsec_test_secret_key_12345';
        $verifier = new WebhookVerifier($secret);

        $payload = json_encode([
            'event' => 'invoice.validated',
            'timestamp' => '2024-01-15T10:30:00Z',
            'data' => ['id' => 'inv-123'],
        ]);

        $signature = $verifier->generateSignature($payload);

        $decoded = $verifier->verifyIgnoringTimestamp($payload, $signature);

        $this->assertEquals('invoice.validated', $decoded['event']);
        $this->assertEquals('inv-123', $decoded['data']['id']);
    }

    #[Test]
    public function it_rejects_invalid_webhook_signature(): void
    {
        $secret = 'whsec_test_secret_key_12345';
        $verifier = new WebhookVerifier($secret);

        $payload = json_encode(['event' => 'test']);
        $invalidSignature = 't=12345,v1=invalid_hash';

        $this->expectException(\Scell\Sdk\Exceptions\ScellException::class);
        $verifier->verify($payload, $invalidSignature);
    }

    #[Test]
    public function it_checks_webhook_validity(): void
    {
        $secret = 'whsec_test';
        $verifier = new WebhookVerifier($secret);

        $payload = '{"event":"test"}';
        $signature = $verifier->generateSignature($payload);

        $this->assertTrue($verifier->isValid($payload, $signature));
        $this->assertFalse($verifier->isValid($payload, 't=1,v1=wrong'));
    }

    #[Test]
    public function it_lists_webhook_events(): void
    {
        $invoiceEvents = WebhookEvent::forDomain('invoice');
        $signatureEvents = WebhookEvent::forDomain('signature');
        $balanceEvents = WebhookEvent::forDomain('balance');

        $this->assertCount(11, $invoiceEvents); // 6 sortantes + 5 entrantes (including paid)
        $this->assertCount(7, $signatureEvents);
        $this->assertCount(2, $balanceEvents);

        $allValues = WebhookEvent::values();
        $this->assertCount(20, $allValues);
        $this->assertContains('invoice.validated', $allValues);
        $this->assertContains('invoice.incoming.received', $allValues);
        $this->assertContains('invoice.incoming.paid', $allValues);
        $this->assertContains('signature.completed', $allValues);
    }

    #[Test]
    public function it_has_incoming_paid_webhook_event(): void
    {
        $event = WebhookEvent::InvoiceIncomingPaid;

        $this->assertEquals('invoice.incoming.paid', $event->value);
        $this->assertEquals('Facture entrante payee', $event->label());
        $this->assertEquals('invoice', $event->domain());
    }

    #[Test]
    public function it_creates_validation_exception_with_errors(): void
    {
        $exception = new ValidationException(
            'Erreur de validation',
            [
                'invoice_number' => ['Le numero de facture est requis'],
                'seller_siret' => ['Le SIRET doit contenir 14 caracteres'],
            ]
        );

        $this->assertEquals(422, $exception->getCode());
        $this->assertTrue($exception->hasFieldError('invoice_number'));
        $this->assertFalse($exception->hasFieldError('buyer_siret'));
        $this->assertEquals(
            'Le numero de facture est requis',
            $exception->getFirstFieldError('invoice_number')
        );
        $this->assertEquals(['invoice_number', 'seller_siret'], $exception->getFailedFields());
        $this->assertCount(2, $exception->getAllMessages());
    }

    #[Test]
    public function it_parses_direction_enum(): void
    {
        $this->assertEquals(Direction::Outgoing, Direction::from('outgoing'));
        $this->assertEquals(Direction::Incoming, Direction::from('incoming'));
        $this->assertEquals('Vente', Direction::Outgoing->label());
        $this->assertEquals('Achat', Direction::Incoming->label());
    }

    #[Test]
    public function it_parses_output_format_enum(): void
    {
        $this->assertEquals(OutputFormat::FacturX, OutputFormat::from('facturx'));
        $this->assertEquals('pdf', OutputFormat::FacturX->extension());
        $this->assertEquals('xml', OutputFormat::UBL->extension());
    }

    #[Test]
    public function it_checks_invoice_status_properties(): void
    {
        $this->assertTrue(InvoiceStatus::Accepted->isFinal());
        $this->assertTrue(InvoiceStatus::Rejected->isFinal());
        $this->assertTrue(InvoiceStatus::Paid->isFinal());
        $this->assertFalse(InvoiceStatus::Draft->isFinal());
        $this->assertTrue(InvoiceStatus::Validated->isActionable());
    }

    #[Test]
    public function it_checks_paid_status_label(): void
    {
        $this->assertEquals('Payee', InvoiceStatus::Paid->label());
    }

    #[Test]
    public function it_checks_signature_status_properties(): void
    {
        $this->assertTrue(SignatureStatus::Completed->isFinal());
        $this->assertFalse(SignatureStatus::Pending->isFinal());
        $this->assertTrue(SignatureStatus::WaitingSigners->canRemind());
        $this->assertFalse(SignatureStatus::Completed->canRemind());
    }

    #[Test]
    public function it_checks_auth_method_requirements(): void
    {
        $this->assertTrue(AuthMethod::Email->requiresEmail());
        $this->assertFalse(AuthMethod::Email->requiresPhone());
        $this->assertTrue(AuthMethod::Sms->requiresPhone());
        $this->assertFalse(AuthMethod::Sms->requiresEmail());
        $this->assertTrue(AuthMethod::Both->requiresEmail());
        $this->assertTrue(AuthMethod::Both->requiresPhone());
    }

    #[Test]
    public function it_creates_config_from_array(): void
    {
        $config = Config::fromArray([
            'base_url' => 'https://custom.api.com',
            'http' => [
                'timeout' => 60,
                'retry_attempts' => 5,
            ],
            'webhook_secret' => 'whsec_test',
        ]);

        $this->assertEquals('https://custom.api.com', $config->baseUrl);
        $this->assertEquals(60, $config->timeout);
        $this->assertEquals(5, $config->retryAttempts);
        $this->assertEquals('whsec_test', $config->webhookSecret);
    }

    #[Test]
    public function it_creates_company_from_array(): void
    {
        $data = [
            'id' => 'company-1',
            'name' => 'Ma Societe SARL',
            'siret' => '12345678901234',
            'address_line1' => '1 Rue Test',
            'postal_code' => '75001',
            'city' => 'Paris',
            'status' => 'active',
            'country' => 'FR',
        ];

        $company = Company::fromArray($data);

        $this->assertEquals('Ma Societe SARL', $company->name);
        $this->assertEquals('12345678901234', $company->siret);
        $this->assertTrue($company->isActive());
        $this->assertFalse($company->isPendingKyc());
    }

    #[Test]
    public function it_parses_paid_invoice_from_api_response(): void
    {
        $data = [
            'id' => '123e4567-e89b-12d3-a456-426614174000',
            'invoice_number' => 'FACT-2024-002',
            'direction' => 'incoming',
            'output_format' => 'facturx',
            'issue_date' => '2024-01-15',
            'total_ht' => 1000.00,
            'total_tax' => 200.00,
            'total_ttc' => 1200.00,
            'seller' => [
                'siret' => '12345678901234',
                'name' => 'Fournisseur SA',
                'address' => [
                    'line1' => '1 Rue Test',
                    'postal_code' => '75001',
                    'city' => 'Paris',
                ],
            ],
            'buyer' => [
                'siret' => '98765432109876',
                'name' => 'Ma Societe',
                'address' => [
                    'line1' => '2 Avenue Client',
                    'postal_code' => '69001',
                    'city' => 'Lyon',
                ],
            ],
            'lines' => [],
            'status' => 'paid',
            'environment' => 'production',
            'paid_at' => '2024-01-20T14:30:00Z',
            'payment_reference' => 'VIR-2024-0120',
            'payment_note' => 'Paiement par virement',
        ];

        $invoice = Invoice::fromArray($data);

        $this->assertEquals(InvoiceStatus::Paid, $invoice->status);
        $this->assertEquals(Direction::Incoming, $invoice->direction);
        $this->assertNotNull($invoice->paidAt);
        $this->assertEquals('2024-01-20', $invoice->paidAt->format('Y-m-d'));
        $this->assertEquals('VIR-2024-0120', $invoice->paymentReference);
        $this->assertEquals('Paiement par virement', $invoice->paymentNote);
        $this->assertTrue($invoice->isPaid());
        $this->assertTrue($invoice->isIncoming());
        $this->assertTrue($invoice->isFinal());
    }

    #[Test]
    public function it_detects_paid_invoice_by_status(): void
    {
        $data = [
            'id' => 'inv-1',
            'invoice_number' => 'FACT-001',
            'direction' => 'incoming',
            'output_format' => 'facturx',
            'issue_date' => '2024-01-15',
            'total_ht' => 100.00,
            'total_tax' => 20.00,
            'total_ttc' => 120.00,
            'seller' => ['siret' => '12345678901234', 'name' => 'Test', 'address' => ['line1' => 'A', 'postal_code' => '75001', 'city' => 'Paris']],
            'buyer' => ['siret' => '98765432109876', 'name' => 'Test2', 'address' => ['line1' => 'B', 'postal_code' => '69001', 'city' => 'Lyon']],
            'lines' => [],
            'status' => 'paid',
            'environment' => 'production',
        ];

        $invoice = Invoice::fromArray($data);

        $this->assertTrue($invoice->isPaid());
    }

    #[Test]
    public function it_detects_paid_invoice_by_paid_at(): void
    {
        $data = [
            'id' => 'inv-2',
            'invoice_number' => 'FACT-002',
            'direction' => 'incoming',
            'output_format' => 'facturx',
            'issue_date' => '2024-01-15',
            'total_ht' => 100.00,
            'total_tax' => 20.00,
            'total_ttc' => 120.00,
            'seller' => ['siret' => '12345678901234', 'name' => 'Test', 'address' => ['line1' => 'A', 'postal_code' => '75001', 'city' => 'Paris']],
            'buyer' => ['siret' => '98765432109876', 'name' => 'Test2', 'address' => ['line1' => 'B', 'postal_code' => '69001', 'city' => 'Lyon']],
            'lines' => [],
            'status' => 'accepted',  // Not paid status, but has paid_at
            'environment' => 'production',
            'paid_at' => '2024-01-20T14:30:00Z',
        ];

        $invoice = Invoice::fromArray($data);

        $this->assertTrue($invoice->isPaid());
    }

    #[Test]
    public function it_detects_unpaid_invoice(): void
    {
        $data = [
            'id' => 'inv-3',
            'invoice_number' => 'FACT-003',
            'direction' => 'incoming',
            'output_format' => 'facturx',
            'issue_date' => '2024-01-15',
            'total_ht' => 100.00,
            'total_tax' => 20.00,
            'total_ttc' => 120.00,
            'seller' => ['siret' => '12345678901234', 'name' => 'Test', 'address' => ['line1' => 'A', 'postal_code' => '75001', 'city' => 'Paris']],
            'buyer' => ['siret' => '98765432109876', 'name' => 'Test2', 'address' => ['line1' => 'B', 'postal_code' => '69001', 'city' => 'Lyon']],
            'lines' => [],
            'status' => 'accepted',
            'environment' => 'production',
        ];

        $invoice = Invoice::fromArray($data);

        $this->assertFalse($invoice->isPaid());
    }

    #[Test]
    public function it_detects_incoming_vs_outgoing_invoice(): void
    {
        $incomingData = [
            'id' => 'inv-4',
            'invoice_number' => 'FACT-004',
            'direction' => 'incoming',
            'output_format' => 'facturx',
            'issue_date' => '2024-01-15',
            'total_ht' => 100.00,
            'total_tax' => 20.00,
            'total_ttc' => 120.00,
            'seller' => ['siret' => '12345678901234', 'name' => 'Test', 'address' => ['line1' => 'A', 'postal_code' => '75001', 'city' => 'Paris']],
            'buyer' => ['siret' => '98765432109876', 'name' => 'Test2', 'address' => ['line1' => 'B', 'postal_code' => '69001', 'city' => 'Lyon']],
            'lines' => [],
            'status' => 'accepted',
            'environment' => 'production',
        ];

        $outgoingData = array_merge($incomingData, ['id' => 'inv-5', 'direction' => 'outgoing']);

        $incomingInvoice = Invoice::fromArray($incomingData);
        $outgoingInvoice = Invoice::fromArray($outgoingData);

        $this->assertTrue($incomingInvoice->isIncoming());
        $this->assertFalse($incomingInvoice->isOutgoing());
        $this->assertFalse($outgoingInvoice->isIncoming());
        $this->assertTrue($outgoingInvoice->isOutgoing());
    }
}
