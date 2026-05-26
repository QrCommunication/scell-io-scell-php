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
use Scell\Sdk\Builders\InvoiceLineBuilder;
use Scell\Sdk\DTOs\LineVatContext;
use Scell\Sdk\DTOs\Vat\BuyerContext;
use Scell\Sdk\DTOs\VatResolution;
use Scell\Sdk\Enums\VatCategory;
use Scell\Sdk\Http\HttpClient;
use Scell\Sdk\Resources\BuyerResource;

/**
 * Tests pour BuyerResource::vatContext() (SDK v2.18.0).
 *
 * Couvre les 4 cas principaux du moteur de regles TVA backend :
 * - FR → FR B2B : STANDARD 20 %
 * - FR → DE B2B avec TVA valide : REVERSE_CHARGE 0 %
 * - FR → US : OUT_OF_SCOPE 0 %
 * - Override art. 259 A CGI via place_of_supply FR (prestation electronique)
 */
class BuyersResourceVatContextTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

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
            'handler'     => $stack,
            'http_errors' => false,
        ]));

        return $http;
    }

    /**
     * @param array<string, mixed> $resolution
     * @param list<string> $warnings
     */
    private function makeVatContextResponse(array $resolution, array $warnings = []): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'resolution' => $resolution,
            'warnings'   => $warnings,
        ]));
    }

    // -------------------------------------------------------------------------
    // Cas 1 : FR → FR B2B (STANDARD 20 %)
    // -------------------------------------------------------------------------

    #[Test]
    public function vat_context_fr_to_fr_b2b_returns_standard_20(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [
                $this->makeVatContextResponse([
                    'rate'             => 20.0,
                    'category'         => 'STANDARD',
                    'en16931_code'     => 'S',
                    'exemption_reason' => null,
                    'justification'    => 'TVA francaise au taux normal (20 %)',
                    'is_auto_resolved' => true,
                    'rule'             => 'R1_fr_b2b_domestic',
                ]),
            ],
            $captured,
        );

        $resource = new BuyerResource($http);

        $resolution = $resource->vatContext(
            buyerOrInput: '019cb416-b6db-730c-b3a5-f8b7a4512eb1',
            line: ['category' => 'STANDARD'],
        );

        // Verifications de la resolution
        $this->assertInstanceOf(VatResolution::class, $resolution);
        $this->assertSame(20.0, $resolution->rate);
        $this->assertSame(VatCategory::Standard, $resolution->category);
        $this->assertSame('S', $resolution->en16931Code);
        $this->assertNull($resolution->exemptionReason);
        $this->assertTrue($resolution->isAutoResolved);
        $this->assertSame('R1_fr_b2b_domestic', $resolution->rule);

        // Verifications du payload envoye
        $this->assertCount(1, $captured);
        $body = json_decode((string) $captured[0]->getBody(), true);
        $this->assertSame('019cb416-b6db-730c-b3a5-f8b7a4512eb1', $body['buyer_id']);
        $this->assertSame('STANDARD', $body['line']['category']);
        $this->assertStringContainsString('tenant/buyers/vat-context', (string) $captured[0]->getUri());
    }

    // -------------------------------------------------------------------------
    // Cas 2 : FR → DE B2B avec numero TVA valide (REVERSE_CHARGE 0 %)
    // -------------------------------------------------------------------------

    #[Test]
    public function vat_context_fr_to_de_b2b_with_valid_vat_returns_reverse_charge(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [
                $this->makeVatContextResponse([
                    'rate'             => 0.0,
                    'category'         => 'REVERSE_CHARGE',
                    'en16931_code'     => 'AE',
                    'exemption_reason' => 'reverse_charge',
                    'justification'    => 'TVA non applicable, art. 259-1 du CGI (autoliquidation)',
                    'is_auto_resolved' => true,
                    'rule'             => 'R2_eu_b2b_vat_valid',
                ]),
            ],
            $captured,
        );

        $resource = new BuyerResource($http);

        // Mode 2 : buyer inline via BuyerContext DTO
        $resolution = $resource->vatContext(
            buyerOrInput: new BuyerContext(
                country: 'DE',
                isIndividual: false,
                vatNumber: 'DE123456789',
                vatNumberValid: true,
            ),
            line: new LineVatContext(category: VatCategory::Standard),
        );

        $this->assertSame(0.0, $resolution->rate);
        $this->assertSame(VatCategory::ReverseCharge, $resolution->category);
        $this->assertSame('AE', $resolution->en16931Code);
        $this->assertSame('reverse_charge', $resolution->exemptionReason);
        $this->assertSame('R2_eu_b2b_vat_valid', $resolution->rule);

        // Payload : buyer inline, pas buyer_id
        $body = json_decode((string) $captured[0]->getBody(), true);
        $this->assertArrayNotHasKey('buyer_id', $body);
        $this->assertSame('DE', $body['buyer']['country']);
        $this->assertSame('DE123456789', $body['buyer']['vat_number']);
        $this->assertTrue($body['buyer']['vat_number_valid']);
        $this->assertSame('STANDARD', $body['line']['category']);
    }

    // -------------------------------------------------------------------------
    // Cas 3 : FR → US (OUT_OF_SCOPE 0 %)
    // -------------------------------------------------------------------------

    #[Test]
    public function vat_context_fr_to_us_returns_out_of_scope(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [
                $this->makeVatContextResponse([
                    'rate'             => 0.0,
                    'category'         => 'OUT_OF_SCOPE',
                    'en16931_code'     => 'O',
                    'exemption_reason' => 'out_of_scope',
                    'justification'    => 'Pays tiers hors UE — TVA non applicable',
                    'is_auto_resolved' => true,
                    'rule'             => 'R3_non_eu',
                ]),
            ],
            $captured,
        );

        $resource = new BuyerResource($http);

        // Mode 2 : buyer inline via array
        $resolution = $resource->vatContext(
            buyerOrInput: ['country' => 'US', 'is_individual' => false],
            line: ['category' => 'STANDARD'],
        );

        $this->assertSame(0.0, $resolution->rate);
        $this->assertSame(VatCategory::OutOfScope, $resolution->category);
        $this->assertSame('O', $resolution->en16931Code);
        $this->assertSame('out_of_scope', $resolution->exemptionReason);

        $body = json_decode((string) $captured[0]->getBody(), true);
        $this->assertSame('US', $body['buyer']['country']);
    }

    // -------------------------------------------------------------------------
    // Cas 4 : Override art. 259 A CGI via place_of_supply = FR
    // (prestation electronique livree a un client DE, mais lieu France)
    // -------------------------------------------------------------------------

    #[Test]
    public function vat_context_259a_override_place_of_supply_fr_returns_standard(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [
                $this->makeVatContextResponse([
                    'rate'             => 20.0,
                    'category'         => 'STANDARD',
                    'en16931_code'     => 'S',
                    'exemption_reason' => null,
                    'justification'    => 'TVA francaise appliquee (art. 259 A CGI — lieu de prestation force a FR)',
                    'is_auto_resolved' => true,
                    'rule'             => 'R4_259a_place_of_supply_override',
                ]),
            ],
            $captured,
        );

        $resource = new BuyerResource($http);

        $resolution = $resource->vatContext(
            buyerOrInput: new BuyerContext(country: 'DE', vatNumber: 'DE123456789'),
            line: new LineVatContext(
                category: VatCategory::Standard,
                placeOfSupply: 'FR',
                serviceNature: 'prestation electronique',
            ),
        );

        $this->assertSame(20.0, $resolution->rate);
        $this->assertSame(VatCategory::Standard, $resolution->category);
        $this->assertSame('S', $resolution->en16931Code);
        $this->assertNull($resolution->exemptionReason);

        // Le payload doit transmettre place_of_supply et service_nature
        $body = json_decode((string) $captured[0]->getBody(), true);
        $this->assertSame('FR', $body['line']['place_of_supply']);
        $this->assertSame('prestation electronique', $body['line']['service_nature']);
        $this->assertSame('STANDARD', $body['line']['category']);
    }

    // -------------------------------------------------------------------------
    // Tests des DTOs
    // -------------------------------------------------------------------------

    #[Test]
    public function vat_resolution_from_array_maps_all_fields(): void
    {
        $data = [
            'rate'             => 0.0,
            'category'         => 'REVERSE_CHARGE',
            'en16931_code'     => 'AE',
            'exemption_reason' => 'reverse_charge',
            'justification'    => 'Autoliquidation art. 259-1 CGI',
            'is_auto_resolved' => true,
            'rule'             => 'R2_eu_b2b_vat_valid',
        ];

        $resolution = VatResolution::fromArray($data);

        $this->assertSame(0.0, $resolution->rate);
        $this->assertSame(VatCategory::ReverseCharge, $resolution->category);
        $this->assertSame('AE', $resolution->en16931Code);
        $this->assertSame('reverse_charge', $resolution->exemptionReason);
        $this->assertSame('Autoliquidation art. 259-1 CGI', $resolution->justification);
        $this->assertTrue($resolution->isAutoResolved);
        $this->assertSame('R2_eu_b2b_vat_valid', $resolution->rule);
    }

    #[Test]
    public function vat_resolution_to_array_is_inverse_of_from_array(): void
    {
        $data = [
            'rate'             => 20.0,
            'category'         => 'STANDARD',
            'en16931_code'     => 'S',
            'exemption_reason' => null,
            'justification'    => 'TVA normale',
            'is_auto_resolved' => true,
            'rule'             => 'R1',
        ];

        $arr = VatResolution::fromArray($data)->toArray();
        $this->assertSame($data['category'], $arr['category']);
        $this->assertSame($data['rate'], $arr['rate']);
        $this->assertSame($data['en16931_code'], $arr['en16931_code']);
    }

    #[Test]
    public function buyer_context_from_array_maps_correctly(): void
    {
        $context = BuyerContext::fromArray([
            'country'          => 'DE',
            'is_individual'    => false,
            'vat_number'       => 'DE123',
            'vat_number_valid' => true,
        ]);

        $this->assertSame('DE', $context->country);
        $this->assertFalse($context->isIndividual);
        $this->assertSame('DE123', $context->vatNumber);
        $this->assertTrue($context->vatNumberValid);
    }

    #[Test]
    public function buyer_context_to_array_excludes_null_values(): void
    {
        $context = new BuyerContext(country: 'US', isIndividual: true);
        $arr = $context->toArray();

        $this->assertSame('US', $arr['country']);
        $this->assertTrue($arr['is_individual']);
        $this->assertArrayNotHasKey('vat_number', $arr);
        $this->assertArrayNotHasKey('vat_number_valid', $arr);
    }

    #[Test]
    public function line_vat_context_from_array_maps_correctly(): void
    {
        $ctx = LineVatContext::fromArray([
            'category'       => 'REVERSE_CHARGE',
            'place_of_supply' => 'FR',
            'service_nature' => 'logiciel',
        ]);

        $this->assertSame(VatCategory::ReverseCharge, $ctx->category);
        $this->assertSame('FR', $ctx->placeOfSupply);
        $this->assertSame('logiciel', $ctx->serviceNature);
    }

    #[Test]
    public function line_vat_context_to_array_excludes_null_values(): void
    {
        $ctx = new LineVatContext(category: VatCategory::Exempt);
        $arr = $ctx->toArray();

        $this->assertSame('EXEMPT', $arr['category']);
        $this->assertArrayNotHasKey('place_of_supply', $arr);
        $this->assertArrayNotHasKey('service_nature', $arr);
    }

    // -------------------------------------------------------------------------
    // Tests de VatCategory (enum)
    // -------------------------------------------------------------------------

    #[Test]
    public function vat_category_default_rates_are_correct(): void
    {
        $this->assertSame(20.0, VatCategory::Standard->defaultRate());
        $this->assertSame(10.0, VatCategory::Intermediate->defaultRate());
        $this->assertSame(5.5, VatCategory::Reduced->defaultRate());
        $this->assertSame(2.1, VatCategory::SuperReduced->defaultRate());
        $this->assertSame(0.0, VatCategory::ZeroRated->defaultRate());
        $this->assertSame(0.0, VatCategory::Exempt->defaultRate());
        $this->assertSame(0.0, VatCategory::ReverseCharge->defaultRate());
        $this->assertSame(0.0, VatCategory::OutOfScope->defaultRate());
    }

    #[Test]
    public function vat_category_en16931_codes_are_correct(): void
    {
        $this->assertSame('S', VatCategory::Standard->en16931Code());
        $this->assertSame('S', VatCategory::Intermediate->en16931Code());
        $this->assertSame('S', VatCategory::Reduced->en16931Code());
        $this->assertSame('S', VatCategory::SuperReduced->en16931Code());
        $this->assertSame('Z', VatCategory::ZeroRated->en16931Code());
        $this->assertSame('E', VatCategory::Exempt->en16931Code());
        $this->assertSame('AE', VatCategory::ReverseCharge->en16931Code());
        $this->assertSame('O', VatCategory::OutOfScope->en16931Code());
    }

    #[Test]
    public function vat_category_exemption_reasons_are_correct(): void
    {
        $this->assertNull(VatCategory::Standard->exemptionReason());
        $this->assertNull(VatCategory::Intermediate->exemptionReason());
        $this->assertNull(VatCategory::Reduced->exemptionReason());
        $this->assertNull(VatCategory::SuperReduced->exemptionReason());
        $this->assertSame('zero_rated', VatCategory::ZeroRated->exemptionReason());
        $this->assertSame('exempt', VatCategory::Exempt->exemptionReason());
        $this->assertSame('reverse_charge', VatCategory::ReverseCharge->exemptionReason());
        $this->assertSame('out_of_scope', VatCategory::OutOfScope->exemptionReason());
    }

    // -------------------------------------------------------------------------
    // Tests de InvoiceLineBuilder
    // -------------------------------------------------------------------------

    #[Test]
    public function invoice_line_builder_default_is_standard_20(): void
    {
        $line = (new InvoiceLineBuilder())
            ->withDescription('Service')
            ->withQuantity(1)
            ->withUnitPrice(100.0)
            ->build();

        $this->assertSame(20.0, $line['tax_rate']);
        $this->assertArrayNotHasKey('metadata', $line);
    }

    #[Test]
    public function invoice_line_builder_with_category_sets_tax_rate_and_metadata(): void
    {
        $line = (new InvoiceLineBuilder())
            ->withDescription('Logiciel EU')
            ->withQuantity(1)
            ->withUnitPrice(500.0)
            ->withCategory(VatCategory::ReverseCharge)
            ->build();

        $this->assertSame(0.0, $line['tax_rate']);
        $this->assertSame('REVERSE_CHARGE', $line['metadata']['category']);
        $this->assertSame('reverse_charge', $line['metadata']['exemption_reason']);
        $this->assertSame(500.0, $line['total_ht']);
        $this->assertSame(0.0, $line['total_tax']);
        $this->assertSame(500.0, $line['total_ttc']);
    }

    #[Test]
    public function invoice_line_builder_with_place_of_supply_adds_to_metadata(): void
    {
        $line = (new InvoiceLineBuilder())
            ->withDescription('Streaming')
            ->withQuantity(1)
            ->withUnitPrice(10.0)
            ->withCategory(VatCategory::Standard)
            ->withPlaceOfSupply('de')
            ->build();

        // place_of_supply doit etre uppercase
        $this->assertSame('DE', $line['metadata']['place_of_supply']);
        $this->assertSame('STANDARD', $line['metadata']['category']);
        // Standard : pas d'exemption_reason
        $this->assertArrayNotHasKey('exemption_reason', $line['metadata']);
    }

    #[Test]
    public function invoice_line_builder_computes_totals_correctly(): void
    {
        $line = (new InvoiceLineBuilder())
            ->withDescription('Conseil')
            ->withQuantity(3)
            ->withUnitPrice(1000.0)
            ->withCategory(VatCategory::Standard)
            ->build();

        $this->assertSame(3000.0, $line['total_ht']);
        $this->assertSame(600.0, $line['total_tax']);
        $this->assertSame(3600.0, $line['total_ttc']);
    }

    #[Test]
    public function invoice_line_builder_is_immutable(): void
    {
        $base = (new InvoiceLineBuilder())
            ->withDescription('Base')
            ->withUnitPrice(100.0);

        $withRC = $base->withCategory(VatCategory::ReverseCharge);
        $withStd = $base->withCategory(VatCategory::Standard);

        $this->assertSame(0.0, $withRC->build()['tax_rate']);
        $this->assertSame(20.0, $withStd->build()['tax_rate']);
    }

    // -------------------------------------------------------------------------
    // Cas 5 : vatContext avec line null (defaut STANDARD)
    // -------------------------------------------------------------------------

    #[Test]
    public function vat_context_with_null_line_defaults_to_standard_category(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [
                $this->makeVatContextResponse([
                    'rate'             => 20.0,
                    'category'         => 'STANDARD',
                    'en16931_code'     => 'S',
                    'exemption_reason' => null,
                    'justification'    => 'TVA normale',
                    'is_auto_resolved' => true,
                    'rule'             => 'R1_fr_b2b_domestic',
                ]),
            ],
            $captured,
        );

        $resource = new BuyerResource($http);
        $resource->vatContext(buyerOrInput: 'some-uuid', line: null);

        $body = json_decode((string) $captured[0]->getBody(), true);
        $this->assertSame('STANDARD', $body['line']['category']);
    }
}
