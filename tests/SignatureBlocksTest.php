<?php

declare(strict_types=1);

namespace Scell\Sdk\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Scell\Sdk\DTOs\BlockPosition;
use Scell\Sdk\DTOs\DateBlock;
use Scell\Sdk\DTOs\InitialsBlock;
use Scell\Sdk\DTOs\Mention;
use Scell\Sdk\Enums\AuthMethod;
use Scell\Sdk\Http\HttpClient;
use Scell\Sdk\Resources\SignatureResource;

/**
 * Couvre les 3 nouveaux champs de POST /api/v1/signatures (v2.12.0) :
 *  - `initials_block` : configuration du bloc paraphe.
 *  - `mentions[]`     : mentions juridiques per-signer.
 *  - `date_block`     : configuration du bloc date du jour.
 *
 * Verifications cles :
 *  - Serialization en snake_case sur la wire.
 *  - Support tableau brut ET DTO pour chaque champ.
 *  - Retrocompat : absence des 3 champs = payload v2.11.0 inchange.
 *  - Validations defensives (modes invalides, customText trop long, etc.).
 */
class SignatureBlocksTest extends TestCase
{
    /**
     * @param Response[]    $responses
     * @param Request[]     $captured Reference rempli par le test.
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
     * Fixture minimale de reponse signature.
     *
     * @return array<string, mixed>
     */
    private function signatureFixture(): array
    {
        return [
            'data' => [
                'id' => 'sig-test',
                'title' => 'Contrat',
                'document_name' => 'contract.pdf',
                'document_size' => 1024,
                'signers' => [],
                'status' => 'pending',
                'environment' => 'sandbox',
            ],
        ];
    }

    /**
     * Decode le body JSON de la derniere requete capturee.
     *
     * @return array<string, mixed>
     */
    private function lastRequestBody(array $captured): array
    {
        $request = end($captured);
        return json_decode((string) $request->getBody(), true);
    }

    #[Test]
    public function builder_serializes_initials_block_from_array(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(201, ['Content-Type' => 'application/json'], json_encode($this->signatureFixture()))],
            $captured,
        );
        $resource = new SignatureResource($http);

        $resource->builder()
            ->title('Test')
            ->document('PDF', 'doc.pdf')
            ->addEmailSigner('Jean', 'Dupont', 'jean@example.com')
            ->initialsBlock([
                'enabled' => true,
                'mode' => 'auto',
                'source' => 'signer_name',
                'pages' => 'all',
                'position' => ['x' => 90, 'y' => 95, 'unit' => 'percent'],
                'font_size' => 10,
                'color' => '#333333',
            ])
            ->create();

        $body = $this->lastRequestBody($captured);

        $this->assertArrayHasKey('initials_block', $body);
        $this->assertSame(true, $body['initials_block']['enabled']);
        $this->assertSame('auto', $body['initials_block']['mode']);
        $this->assertSame('signer_name', $body['initials_block']['source']);
        $this->assertSame('all', $body['initials_block']['pages']);
        $this->assertEquals(90, $body['initials_block']['position']['x']);
        $this->assertSame('percent', $body['initials_block']['position']['unit']);
    }

    #[Test]
    public function builder_serializes_initials_block_from_dto(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(201, ['Content-Type' => 'application/json'], json_encode($this->signatureFixture()))],
            $captured,
        );
        $resource = new SignatureResource($http);

        $block = new InitialsBlock(
            enabled: true,
            position: new BlockPosition(x: 90, y: 95, page: 'last'),
            mode: 'custom',
            source: 'custom',
            customText: 'JD',
            pages: [1, 2, 3],
            fontSize: 12,
            color: '#FF0000',
        );

        $resource->builder()
            ->title('Test')
            ->document('PDF', 'doc.pdf')
            ->addEmailSigner('Jean', 'Dupont', 'jean@example.com')
            ->initialsBlock($block)
            ->create();

        $body = $this->lastRequestBody($captured);

        $this->assertSame('custom', $body['initials_block']['mode']);
        $this->assertSame('JD', $body['initials_block']['custom_text']);
        $this->assertSame([1, 2, 3], $body['initials_block']['pages']);
        $this->assertSame(12, $body['initials_block']['font_size']);
        $this->assertSame('last', $body['initials_block']['position']['page']);
    }

    #[Test]
    public function builder_serializes_mentions_array_with_mixed_inputs(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(201, ['Content-Type' => 'application/json'], json_encode($this->signatureFixture()))],
            $captured,
        );
        $resource = new SignatureResource($http);

        // Mix : un tableau brut + un DTO Mention.
        $mentionDto = new Mention(
            label: 'Bon pour accord',
            signerIndex: 1,
            position: new BlockPosition(x: 20, y: 60, page: 2, w: 50, h: 6),
            required: true,
            fallbackText: 'Bon pour accord',
        );

        $resource->builder()
            ->title('Test')
            ->document('PDF', 'doc.pdf')
            ->addEmailSigner('A', 'A', 'a@example.com')
            ->addEmailSigner('B', 'B', 'b@example.com')
            ->mentions([
                [
                    'label' => 'Lu et approuve',
                    'required' => true,
                    'signer_index' => 0,
                    'position' => ['page' => 1, 'x' => 10, 'y' => 80, 'w' => 60, 'h' => 8, 'unit' => 'percent'],
                    'fallback_text' => 'Lu et approuve',
                    'font_size' => 10,
                    'color' => '#000000',
                ],
                $mentionDto,
            ])
            ->create();

        $body = $this->lastRequestBody($captured);

        $this->assertCount(2, $body['mentions']);
        $this->assertSame('Lu et approuve', $body['mentions'][0]['label']);
        $this->assertSame(0, $body['mentions'][0]['signer_index']);
        $this->assertSame(1, $body['mentions'][0]['position']['page']);

        // DTO normalise en snake_case.
        $this->assertSame('Bon pour accord', $body['mentions'][1]['label']);
        $this->assertSame(1, $body['mentions'][1]['signer_index']);
        $this->assertSame('Bon pour accord', $body['mentions'][1]['fallback_text']);
        $this->assertEquals(50, $body['mentions'][1]['position']['w']);
    }

    #[Test]
    public function builder_add_mention_appends_incrementally(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(201, ['Content-Type' => 'application/json'], json_encode($this->signatureFixture()))],
            $captured,
        );
        $resource = new SignatureResource($http);

        $resource->builder()
            ->title('Test')
            ->document('PDF', 'doc.pdf')
            ->addEmailSigner('Jean', 'Dupont', 'jean@example.com')
            ->addMention([
                'label' => 'Lu et approuve',
                'signer_index' => 0,
                'position' => ['page' => 1, 'x' => 10, 'y' => 80, 'unit' => 'percent'],
            ])
            ->addMention(new Mention(
                label: 'Bon pour accord',
                signerIndex: 0,
                position: new BlockPosition(x: 10, y: 70, page: 1),
            ))
            ->create();

        $body = $this->lastRequestBody($captured);

        $this->assertCount(2, $body['mentions']);
        $this->assertSame('Lu et approuve', $body['mentions'][0]['label']);
        $this->assertSame('Bon pour accord', $body['mentions'][1]['label']);
    }

    #[Test]
    public function builder_serializes_date_block_with_dto_and_string_page(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(201, ['Content-Type' => 'application/json'], json_encode($this->signatureFixture()))],
            $captured,
        );
        $resource = new SignatureResource($http);

        $dateBlock = new DateBlock(
            enabled: true,
            position: new BlockPosition(x: 80, y: 10, page: 'last'),
            format: 'd/m/Y',
            timezone: 'Europe/Paris',
        );

        $resource->builder()
            ->title('Test')
            ->document('PDF', 'doc.pdf')
            ->addEmailSigner('Jean', 'Dupont', 'jean@example.com')
            ->dateBlock($dateBlock)
            ->create();

        $body = $this->lastRequestBody($captured);

        $this->assertArrayHasKey('date_block', $body);
        $this->assertTrue($body['date_block']['enabled']);
        $this->assertSame('d/m/Y', $body['date_block']['format']);
        $this->assertSame('Europe/Paris', $body['date_block']['timezone']);
        $this->assertSame('last', $body['date_block']['position']['page']);
        $this->assertEquals(80, $body['date_block']['position']['x']);
    }

    #[Test]
    public function payload_omits_new_fields_when_not_set_backward_compat(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(201, ['Content-Type' => 'application/json'], json_encode($this->signatureFixture()))],
            $captured,
        );
        $resource = new SignatureResource($http);

        // Pas d'appel a initialsBlock/mentions/dateBlock : payload v2.11 inchange.
        $resource->builder()
            ->title('Test')
            ->document('PDF', 'doc.pdf')
            ->addEmailSigner('Jean', 'Dupont', 'jean@example.com')
            ->create();

        $body = $this->lastRequestBody($captured);

        $this->assertArrayNotHasKey('initials_block', $body);
        $this->assertArrayNotHasKey('mentions', $body);
        $this->assertArrayNotHasKey('date_block', $body);
    }

    #[Test]
    public function initials_block_dto_rejects_invalid_mode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid mode 'invalid'");

        new InitialsBlock(
            enabled: true,
            position: new BlockPosition(x: 90, y: 95),
            mode: 'invalid',
        );
    }

    #[Test]
    public function initials_block_dto_rejects_custom_text_too_long(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('customText must be 8 characters or less');

        new InitialsBlock(
            enabled: true,
            position: new BlockPosition(x: 90, y: 95),
            source: 'custom',
            customText: 'TOOLONGTEXT',
        );
    }

    #[Test]
    public function mention_dto_requires_page_as_int(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Mention position.page must be an int');

        new Mention(
            label: 'Lu et approuve',
            signerIndex: 0,
            position: new BlockPosition(x: 10, y: 80, page: 'last'),
        );
    }

    #[Test]
    public function block_position_round_trips_via_array(): void
    {
        $pos = new BlockPosition(x: 12.5, y: 34.0, page: 3, w: 60, h: 8, unit: 'percent');

        $arr = $pos->toArray();
        $this->assertSame(12.5, $arr['x']);
        $this->assertSame(3, $arr['page']);
        $this->assertSame(60.0, $arr['w']);

        $rebuilt = BlockPosition::fromArray($arr);
        $this->assertSame($pos->x, $rebuilt->x);
        $this->assertSame($pos->y, $rebuilt->y);
        $this->assertSame($pos->page, $rebuilt->page);
        $this->assertSame($pos->w, $rebuilt->w);
        $this->assertSame($pos->h, $rebuilt->h);
        $this->assertSame($pos->unit, $rebuilt->unit);
    }
}
