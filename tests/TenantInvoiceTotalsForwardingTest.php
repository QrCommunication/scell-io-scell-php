<?php

declare(strict_types=1);

namespace Scell\Sdk\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Scell\Sdk\Http\HttpClient;
use Scell\Sdk\Resources\TenantDirectInvoiceResource;
use Scell\Sdk\Resources\TenantInvoiceResource;

/**
 * Vérifie que les totaux niveau facture (total_ht / total_tax / total_ttc) sont
 * bien forwardés par les mappers de création des factures tenant.
 *
 * Régression : avant le fix v2.36.0, ces totaux étaient droppés (l'allowlist de
 * normalizeCreatePayload les ignorait), ce qui faisait échouer la création
 * serveur en 422 ("Le total ... est obligatoire").
 *
 * Note : la clé TVA côté API est `total_tax` (et NON `total_tva`).
 */
final class TenantInvoiceTotalsForwardingTest extends TestCase
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalize(object $resource, array $data): array
    {
        $method = new ReflectionMethod($resource, 'normalizeCreatePayload');
        $method->setAccessible(true);

        /** @var array<string, mixed> $payload */
        $payload = $method->invoke($resource, $data);

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function basePayload(): array
    {
        return [
            'direction' => 'outgoing',
            'output_format' => 'facturx',
            'issue_date' => '2026-06-07',
            'seller' => ['name' => 'ACME SAS', 'siret' => '12345678900012'],
            'buyer' => ['name' => 'Client Exemple', 'is_individual' => true],
            'lines' => [[
                'description' => 'Prestation',
                'quantity' => 1,
                'unit_price' => 100.0,
                'tax_rate' => 20.0,
                'total_ht' => 100.0,
                'total_ttc' => 120.0,
            ]],
            'total_ht' => 100.0,
            'total_tax' => 20.0,
            'total_ttc' => 120.0,
        ];
    }

    private function http(): HttpClient
    {
        return new HttpClient('https://api.scell.io/api/v1');
    }

    #[Test]
    public function tenant_invoice_resource_forwards_invoice_totals(): void
    {
        $payload = $this->normalize(new TenantInvoiceResource($this->http()), $this->basePayload());

        $this->assertSame(100.0, $payload['total_ht']);
        $this->assertSame(20.0, $payload['total_tax']);
        $this->assertSame(120.0, $payload['total_ttc']);
    }

    #[Test]
    public function tenant_direct_invoice_resource_forwards_invoice_totals(): void
    {
        $payload = $this->normalize(new TenantDirectInvoiceResource($this->http()), $this->basePayload());

        $this->assertSame(100.0, $payload['total_ht']);
        $this->assertSame(20.0, $payload['total_tax']);
        $this->assertSame(120.0, $payload['total_ttc']);
    }

    #[Test]
    public function totals_are_omitted_when_absent(): void
    {
        $data = $this->basePayload();
        unset($data['total_ht'], $data['total_tax'], $data['total_ttc']);

        $payload = $this->normalize(new TenantInvoiceResource($this->http()), $data);

        $this->assertArrayNotHasKey('total_ht', $payload);
        $this->assertArrayNotHasKey('total_tax', $payload);
        $this->assertArrayNotHasKey('total_ttc', $payload);
    }
}
