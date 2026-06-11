<?php

declare(strict_types=1);

namespace Scell\Sdk\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Scell\Sdk\DTOs\Invoice;

/**
 * Couvre le champ `Invoice::$subTenantId` (SDK 3.3.0) — expose par l'API sur
 * la surface tenant des factures entrantes pour permettre la garde IDOR
 * cote plateforme consommatrice (un tenant = N sub-tenants).
 */
class InvoiceSubTenantIdTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function basePayload(): array
    {
        return [
            'id' => 'invoice-uuid-1',
            'invoice_number' => 'FOURN-2026-001',
            'direction' => 'incoming',
            'output_format' => 'facturx',
            'issue_date' => '2026-06-11',
            'total_ht' => 100.0,
            'total_tax' => 20.0,
            'total_ttc' => 120.0,
            'seller' => [
                'name' => 'Fournisseur SA',
                'address' => ['street' => '5 rue Test', 'postal_code' => '69001', 'city' => 'Lyon', 'country' => 'FR'],
            ],
            'buyer' => [
                'name' => 'Cabinet Client',
                'address' => ['street' => '1 rue Test', 'postal_code' => '75001', 'city' => 'Paris', 'country' => 'FR'],
            ],
            'lines' => [],
            'status' => 'received',
            'environment' => 'sandbox',
        ];
    }

    #[Test]
    public function from_array_maps_sub_tenant_id_when_present(): void
    {
        $invoice = Invoice::fromArray($this->basePayload() + [
            'sub_tenant_id' => 'sub-tenant-uuid-42',
        ]);

        $this->assertSame('sub-tenant-uuid-42', $invoice->subTenantId);
    }

    #[Test]
    public function from_array_defaults_sub_tenant_id_to_null(): void
    {
        // Reponses dashboard / factures sortantes : le champ est absent.
        $invoice = Invoice::fromArray($this->basePayload());

        $this->assertNull($invoice->subTenantId);
    }

    #[Test]
    public function from_array_ignores_non_string_sub_tenant_id(): void
    {
        // whenLoaded() Laravel peut serialiser un MissingValue exotique —
        // tout ce qui n'est pas une string devient null.
        $invoice = Invoice::fromArray($this->basePayload() + [
            'sub_tenant_id' => ['unexpected' => 'shape'],
        ]);

        $this->assertNull($invoice->subTenantId);
    }
}
