<?php

declare(strict_types=1);

namespace Scell\Sdk\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Scell\Sdk\DTOs\Invoice;
use Scell\Sdk\Enums\InvoiceStatus;
use Scell\Sdk\Enums\RefundStatus;

/**
 * Couvre les champs `refund_status` et `total_refunded` exposes par l'API
 * Scell.io (SDK 2.20.0), ainsi que les nouveaux statuts Invoice `refunded`,
 * `partially_refunded`, `validating`, `transmitting`, `disputed`, `received`,
 * `completed` alignes sur le check constraint backend `invoices_status_check`.
 *
 * Le backend pose les statuts `refunded` / `partially_refunded` via
 * `CreditNoteObserver` lorsque la somme des avoirs valides atteint (ou non)
 * le total TTC de la facture. Le champ `refund_status` est une projection
 * derivee `none|partial|full` plus simple a consommer.
 */
class InvoiceRefundStatusTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function baseInvoicePayload(): array
    {
        return [
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
            'status' => 'paid',
            'environment' => 'sandbox',
        ];
    }

    #[Test]
    public function it_maps_refund_status_full_and_total_refunded_from_api(): void
    {
        $payload = $this->baseInvoicePayload();
        $payload['status'] = 'refunded';
        $payload['refund_status'] = 'full';
        $payload['total_refunded'] = 1200.00;

        $invoice = Invoice::fromArray($payload);

        $this->assertSame(InvoiceStatus::Refunded, $invoice->status);
        $this->assertSame(RefundStatus::Full, $invoice->refundStatus);
        $this->assertSame(1200.00, $invoice->totalRefunded);

        $this->assertTrue($invoice->isRefunded());
        $this->assertTrue($invoice->isFullyRefunded());
        $this->assertFalse($invoice->isPartiallyRefunded());
        $this->assertTrue($invoice->status->isRefunded());
        $this->assertTrue($invoice->status->isFinal());
    }

    #[Test]
    public function it_maps_refund_status_partial(): void
    {
        $payload = $this->baseInvoicePayload();
        $payload['status'] = 'partially_refunded';
        $payload['refund_status'] = 'partial';
        $payload['total_refunded'] = 400.00;

        $invoice = Invoice::fromArray($payload);

        $this->assertSame(InvoiceStatus::PartiallyRefunded, $invoice->status);
        $this->assertSame(RefundStatus::Partial, $invoice->refundStatus);
        $this->assertSame(400.00, $invoice->totalRefunded);

        $this->assertTrue($invoice->isRefunded());
        $this->assertTrue($invoice->isPartiallyRefunded());
        $this->assertFalse($invoice->isFullyRefunded());
        $this->assertTrue($invoice->status->isRefunded());
    }

    #[Test]
    public function it_defaults_refund_status_to_none_when_field_missing(): void
    {
        // Retrocompat : ancien backend qui ne retourne pas refund_status / total_refunded
        $payload = $this->baseInvoicePayload();
        $payload['status'] = 'paid';

        $invoice = Invoice::fromArray($payload);

        $this->assertSame(RefundStatus::None, $invoice->refundStatus);
        $this->assertSame(0.0, $invoice->totalRefunded);

        $this->assertFalse($invoice->isRefunded());
        $this->assertFalse($invoice->isFullyRefunded());
        $this->assertFalse($invoice->isPartiallyRefunded());
        $this->assertFalse($invoice->status->isRefunded());
    }

    #[Test]
    public function it_handles_unknown_refund_status_gracefully(): void
    {
        // Si le backend introduit une valeur inattendue, on fallback sur None
        // pour ne pas casser le SDK cote consommateur.
        $payload = $this->baseInvoicePayload();
        $payload['refund_status'] = 'unknown_future_value';

        $invoice = Invoice::fromArray($payload);

        $this->assertSame(RefundStatus::None, $invoice->refundStatus);
    }

    #[Test]
    public function it_supports_all_invoice_status_check_constraint_values(): void
    {
        // Verifie que chaque valeur du check constraint backend
        // `invoices_status_check` est mappable depuis l'API.
        $statuses = [
            'draft' => InvoiceStatus::Draft,
            'validating' => InvoiceStatus::Validating,
            'validated' => InvoiceStatus::Validated,
            'converting' => InvoiceStatus::Converting,
            'converted' => InvoiceStatus::Converted,
            'transmitting' => InvoiceStatus::Transmitting,
            'transmitted' => InvoiceStatus::Transmitted,
            'accepted' => InvoiceStatus::Accepted,
            'rejected' => InvoiceStatus::Rejected,
            'disputed' => InvoiceStatus::Disputed,
            'paid' => InvoiceStatus::Paid,
            'received' => InvoiceStatus::Received,
            'completed' => InvoiceStatus::Completed,
            'error' => InvoiceStatus::Error,
            'refunded' => InvoiceStatus::Refunded,
            'partially_refunded' => InvoiceStatus::PartiallyRefunded,
        ];

        foreach ($statuses as $value => $expectedCase) {
            $payload = $this->baseInvoicePayload();
            $payload['status'] = $value;

            $invoice = Invoice::fromArray($payload);
            $this->assertSame(
                $expectedCase,
                $invoice->status,
                "Status '{$value}' should map to InvoiceStatus::{$expectedCase->name}"
            );
            // Toutes les valeurs doivent avoir un label non-vide.
            $this->assertNotEmpty($invoice->status->label());
        }
    }

    #[Test]
    public function refund_status_helpers_work_independently_of_status_field(): void
    {
        // Cas defensif : si pour une raison X le backend retourne refund_status
        // sans avoir encore propage le status (ordre de propagation), le SDK
        // doit quand meme reporter isRefunded() = true.
        $payload = $this->baseInvoicePayload();
        $payload['status'] = 'paid'; // pas encore mis a jour
        $payload['refund_status'] = 'partial';
        $payload['total_refunded'] = 300.00;

        $invoice = Invoice::fromArray($payload);

        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame(RefundStatus::Partial, $invoice->refundStatus);
        $this->assertTrue($invoice->isRefunded());
        $this->assertTrue($invoice->isPartiallyRefunded());
        $this->assertFalse($invoice->isFullyRefunded());
    }

    #[Test]
    public function refund_status_enum_exposes_label_and_has_refund(): void
    {
        $this->assertSame('Aucun remboursement', RefundStatus::None->label());
        $this->assertSame('Partiellement remboursee', RefundStatus::Partial->label());
        $this->assertSame('Totalement remboursee', RefundStatus::Full->label());

        $this->assertFalse(RefundStatus::None->hasRefund());
        $this->assertTrue(RefundStatus::Partial->hasRefund());
        $this->assertTrue(RefundStatus::Full->hasRefund());
    }
}
