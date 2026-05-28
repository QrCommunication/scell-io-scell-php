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
use Scell\Sdk\DTOs\PaymentSchedulePreset;
use Scell\Sdk\Http\HttpClient;
use Scell\Sdk\Resources\QuotePaymentScheduleResource;

/**
 * Couvre le nouveau DTO `PaymentSchedulePreset` et la methode
 * `QuotePaymentScheduleResource::presetsDtos()` (SDK 2.24.0).
 *
 * La methode legacy `presets()` (tableau brut) reste inchangee, couverte
 * dans `QuotePaymentScheduleResourceTest`.
 */
class PaymentSchedulePresetDtoTest extends TestCase
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
     * @return array<int, array{key: string, label: string, description: string, lines: list<array{percentage: int, label: string, due_offset_days: int|null}>}>
     */
    private function fiveNativePresets(): array
    {
        return [
            [
                'key' => 'full_upfront',
                'label' => 'Paiement comptant',
                'description' => '100 % a la signature.',
                'lines' => [
                    ['percentage' => 100, 'label' => 'Acompte total', 'due_offset_days' => null],
                ],
            ],
            [
                'key' => 'deposit_50_balance_50',
                'label' => 'Acompte 50 % / Solde 50 %',
                'description' => '50 / 50 a 30 jours.',
                'lines' => [
                    ['percentage' => 50, 'label' => 'Acompte', 'due_offset_days' => null],
                    ['percentage' => 50, 'label' => 'Solde', 'due_offset_days' => 30],
                ],
            ],
            [
                'key' => 'deposit_30_balance_70',
                'label' => 'Acompte 30 % / Solde 70 %',
                'description' => '30 / 70 a 30 jours.',
                'lines' => [
                    ['percentage' => 30, 'label' => 'Acompte', 'due_offset_days' => null],
                    ['percentage' => 70, 'label' => 'Solde', 'due_offset_days' => 30],
                ],
            ],
            [
                'key' => 'thirds_30_30_40',
                'label' => '30 / 30 / 40',
                'description' => '30 % a la commande, 30 % a mi-projet, 40 % a la livraison.',
                'lines' => [
                    ['percentage' => 30, 'label' => '1ere tranche', 'due_offset_days' => null],
                    ['percentage' => 30, 'label' => '2eme tranche', 'due_offset_days' => 30],
                    ['percentage' => 40, 'label' => 'Solde', 'due_offset_days' => 60],
                ],
            ],
            [
                'key' => 'quarterly',
                'label' => 'Trimestriel (4 x 25 %)',
                'description' => '4 versements egaux a 30, 60, 90, 120 jours.',
                'lines' => [
                    ['percentage' => 25, 'label' => 'Versement 1', 'due_offset_days' => 30],
                    ['percentage' => 25, 'label' => 'Versement 2', 'due_offset_days' => 60],
                    ['percentage' => 25, 'label' => 'Versement 3', 'due_offset_days' => 90],
                    ['percentage' => 25, 'label' => 'Versement 4', 'due_offset_days' => 120],
                ],
            ],
        ];
    }

    #[Test]
    public function preset_dto_maps_all_fields_from_api_payload(): void
    {
        $payload = $this->fiveNativePresets()[1]; // deposit_50_balance_50

        $preset = PaymentSchedulePreset::fromArray($payload);

        $this->assertSame('deposit_50_balance_50', $preset->key);
        $this->assertSame('Acompte 50 % / Solde 50 %', $preset->label);
        $this->assertSame('50 / 50 a 30 jours.', $preset->description);
        $this->assertCount(2, $preset->lines);
        $this->assertSame(50, $preset->lines[0]['percentage']);
        $this->assertSame('Acompte', $preset->lines[0]['label']);
        $this->assertNull($preset->lines[0]['due_offset_days']);
        $this->assertSame(30, $preset->lines[1]['due_offset_days']);
    }

    #[Test]
    public function preset_line_count_helper_returns_number_of_lines(): void
    {
        $upfront = PaymentSchedulePreset::fromArray($this->fiveNativePresets()[0]);
        $quarterly = PaymentSchedulePreset::fromArray($this->fiveNativePresets()[4]);

        $this->assertSame(1, $upfront->lineCount());
        $this->assertSame(4, $quarterly->lineCount());
    }

    #[Test]
    public function preset_is_valid_when_sum_of_percentages_is_100(): void
    {
        foreach ($this->fiveNativePresets() as $payload) {
            $preset = PaymentSchedulePreset::fromArray($payload);
            $this->assertTrue(
                $preset->isValid(),
                "Preset '{$preset->key}' should sum to 100"
            );
        }
    }

    #[Test]
    public function preset_is_invalid_when_sum_does_not_equal_100(): void
    {
        $preset = PaymentSchedulePreset::fromArray([
            'key' => 'broken',
            'label' => 'Broken',
            'description' => 'Sum = 90',
            'lines' => [
                ['percentage' => 50, 'label' => 'A', 'due_offset_days' => null],
                ['percentage' => 40, 'label' => 'B', 'due_offset_days' => 30],
            ],
        ]);

        $this->assertFalse($preset->isValid());
    }

    #[Test]
    public function to_schedule_lines_maps_percentage_to_amount_value(): void
    {
        $preset = PaymentSchedulePreset::fromArray($this->fiveNativePresets()[3]); // thirds_30_30_40

        $lines = $preset->toScheduleLines();

        $this->assertCount(3, $lines);
        $this->assertSame('percent', $lines[0]['amount_type']);
        $this->assertSame(30, $lines[0]['amount_value']);
        $this->assertSame('1ere tranche', $lines[0]['milestone_label']);
        $this->assertNull($lines[0]['due_date_offset_days']);

        $this->assertSame(40, $lines[2]['amount_value']);
        $this->assertSame(60, $lines[2]['due_date_offset_days']);
    }

    #[Test]
    public function resource_presets_dtos_returns_array_of_dtos(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => $this->fiveNativePresets(),
            ]))],
            $captured,
        );

        $dtos = (new QuotePaymentScheduleResource($http))->presetsDtos();

        $this->assertCount(5, $dtos);
        $this->assertContainsOnlyInstancesOf(PaymentSchedulePreset::class, $dtos);
        $this->assertSame('full_upfront', $dtos[0]->key);
        $this->assertSame('quarterly', $dtos[4]->key);
    }

    #[Test]
    public function resource_presets_still_returns_raw_array_for_backcompat(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => $this->fiveNativePresets(),
            ]))],
            $captured,
        );

        $raw = (new QuotePaymentScheduleResource($http))->presets();

        $this->assertIsArray($raw);
        $this->assertIsArray($raw[0]);
        $this->assertSame('full_upfront', $raw[0]['key']);
        $this->assertSame('Paiement comptant', $raw[0]['label']);
    }
}
