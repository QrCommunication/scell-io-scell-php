<?php

declare(strict_types=1);

namespace Scell\Sdk\Tests;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Scell\Sdk\Builders\InvoiceLineBuilder;
use Scell\Sdk\Enums\VatCategory;
use Scell\Sdk\Exceptions\VatCorrectionRequiredException;
use Scell\Sdk\Http\HttpClient;

/**
 * Couvre la propagation TVA intra-UE (SDK 2.33.0) :
 *  - InvoiceLineBuilder émet les champs de pilotage en TOP-LEVEL
 *    (vat_category / supply_type / place_of_supply / vat_override_reason),
 *    et NON plus dans metadata.* (cause historique du bug : metadata.category
 *    ignoré par la résolution serveur → 0 % sans mention AE/K).
 *  - VatCategory porte les 4 nouvelles catégories + justification() bilingue.
 *  - Un 409 VAT_CORRECTION_REQUIRED est mappé vers VatCorrectionRequiredException
 *    avec getCorrections() / getHint().
 */
class VatLineAutoliquidationTest extends TestCase
{
    #[Test]
    public function builder_emits_vat_fields_top_level_not_in_metadata(): void
    {
        $line = (new InvoiceLineBuilder())
            ->withDescription('Logiciel SaaS')
            ->withUnitPrice(500.0)
            ->withCategory(VatCategory::ReverseCharge)
            ->withSupplyType('services')
            ->build();

        // Top-level : c'est ce que la résolution autoritaire serveur lit.
        $this->assertSame('REVERSE_CHARGE', $line['vat_category']);
        $this->assertSame('services', $line['supply_type']);
        $this->assertSame(0.0, $line['tax_rate']);

        // Plus AUCUNE catégorie cachée dans metadata.
        $this->assertArrayNotHasKey('metadata', $line);
    }

    #[Test]
    public function builder_emits_goods_intracom_and_override(): void
    {
        $goods = (new InvoiceLineBuilder())
            ->withDescription('Matériel')
            ->withUnitPrice(200.0)
            ->withQuantity(10)
            ->withCategory(VatCategory::IntracomGoods)
            ->withSupplyType('goods')
            ->withPlaceOfSupply('de')
            ->build();

        $this->assertSame('INTRACOM_GOODS', $goods['vat_category']);
        $this->assertSame('goods', $goods['supply_type']);
        $this->assertSame('DE', $goods['place_of_supply']);

        $override = (new InvoiceLineBuilder())
            ->withDescription('Prestation')
            ->withUnitPrice(1000.0)
            ->withTaxRate(20.0)
            ->withOverrideReason('Numéro TVA acheteur invalide à la date d\'émission')
            ->build();

        $this->assertSame(20.0, $override['tax_rate']);
        $this->assertSame('Numéro TVA acheteur invalide à la date d\'émission', $override['vat_override_reason']);
    }

    #[Test]
    public function vat_category_exposes_new_cases_and_bilingual_mentions(): void
    {
        $this->assertSame('K', VatCategory::IntracomGoods->en16931Code());
        $this->assertSame('G', VatCategory::Export->en16931Code());
        $this->assertSame('E', VatCategory::FranchiseBase->en16931Code());

        $this->assertSame('Autoliquidation - Article 283-2 du CGI', VatCategory::ReverseCharge->justification());
        $this->assertSame('TVA non applicable, article 293 B du CGI', VatCategory::FranchiseBase->justification());
        $this->assertStringContainsString('262 ter', (string) VatCategory::IntracomGoods->justification());
        $this->assertStringContainsString('Reverse charge', (string) VatCategory::ReverseCharge->justification('en'));

        // ZERO_RATED : pas de mention BT-120 (EN16931 BR-Z), pas de reason.
        $this->assertNull(VatCategory::ZeroRated->justification());
        $this->assertNull(VatCategory::ZeroRated->exemptionReason());
    }

    #[Test]
    public function http_client_maps_409_to_vat_correction_exception(): void
    {
        $body = [
            'error' => 'VAT_CORRECTION_REQUIRED',
            'message' => 'Taux de TVA incohérent.',
            'corrections' => [[
                'line_index' => 0,
                'description' => 'Logiciel SaaS',
                'provided_rate' => 20,
                'suggested_rate' => 0,
                'suggested_category' => 'REVERSE_CHARGE',
                'en16931_code' => 'AE',
                'mention' => 'Autoliquidation - Article 283-2 du CGI',
                'rule' => 'R2_eu_b2b_vat_valid',
                'warning' => null,
            ]],
            'hint' => 'Acceptez les taux suggérés ou renseignez vat_override_reason.',
        ];

        $mock = new MockHandler([new Response(409, [], (string) json_encode($body))]);
        $http = new HttpClient('https://api.scell.io/api/v1');
        $this->injectHandler($http, $mock);

        try {
            $http->post('invoices', ['lines' => []]);
            $this->fail('VatCorrectionRequiredException attendue');
        } catch (VatCorrectionRequiredException $e) {
            $this->assertSame(409, $e->getHttpStatusCode());
            $this->assertSame('VAT_CORRECTION_REQUIRED', $e->getScellCode());
            $corrections = $e->getCorrections();
            $this->assertCount(1, $corrections);
            $this->assertSame('REVERSE_CHARGE', $corrections[0]['suggested_category']);
            $this->assertSame('AE', $corrections[0]['en16931_code']);
            $this->assertNotNull($e->getHint());
        }
    }

    private function injectHandler(HttpClient $http, MockHandler $mock): void
    {
        $guzzle = new \GuzzleHttp\Client([
            'handler' => \GuzzleHttp\HandlerStack::create($mock),
            'http_errors' => false,
        ]);
        $prop = new ReflectionProperty(HttpClient::class, 'client');
        $prop->setAccessible(true);
        $prop->setValue($http, $guzzle);
    }
}
