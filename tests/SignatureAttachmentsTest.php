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
use Scell\Sdk\DTOs\InitialsBlock;
use Scell\Sdk\DTOs\InitialsPosition;
use Scell\Sdk\DTOs\Mention;
use Scell\Sdk\Http\HttpClient;
use Scell\Sdk\Resources\SignatureResource;

/**
 * Couvre les nouveaux champs multi-document de POST /api/v1/signatures (v2.16.0) :
 *  - `attachments[]`  : jusqu'a 10 PJs PDF mergees cote backend.
 *  - `document_index` : cible un document specifique (0 = principal, 1..N = PJs)
 *    sur les positions de signature, mentions, paraphes, date.
 *
 * Verifications cles :
 *  - Serialization en snake_case sur la wire (attachments + document_index).
 *  - Validation des limites (10 max, plage 0..10 sur document_index).
 *  - Retrocompat : absence de attachments = payload v2.15 inchange.
 */
class SignatureAttachmentsTest extends TestCase
{
    /**
     * @param Response[] $responses
     * @param Request[]  $captured Reference rempli par le test.
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
     * @return array<string, mixed>
     */
    private function lastRequestBody(array $captured): array
    {
        $request = end($captured);
        return json_decode((string) $request->getBody(), true);
    }

    /* =========================================================================
     | attachments[] serialization
     | ======================================================================= */

    #[Test]
    public function builder_serializes_attachments_from_addAttachment(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(201, ['Content-Type' => 'application/json'], json_encode($this->signatureFixture()))],
            $captured,
        );
        $resource = new SignatureResource($http);

        $resource->builder()
            ->title('Contrat + annexes')
            ->document('PDF', 'contrat.pdf')
            ->addAttachment(base64_encode('CGV_BYTES'), 'cgv.pdf')
            ->addAttachment(base64_encode('ANNEXE_BYTES'), 'annexe.pdf')
            ->addEmailSigner('Jean', 'Dupont', 'jean@example.com')
            ->create();

        $body = $this->lastRequestBody($captured);

        $this->assertArrayHasKey('attachments', $body);
        $this->assertCount(2, $body['attachments']);
        $this->assertSame('cgv.pdf', $body['attachments'][0]['document_name']);
        $this->assertSame(base64_encode('CGV_BYTES'), $body['attachments'][0]['document']);
        $this->assertSame('annexe.pdf', $body['attachments'][1]['document_name']);
    }

    #[Test]
    public function builder_serializes_attachments_from_array_setter(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(201, ['Content-Type' => 'application/json'], json_encode($this->signatureFixture()))],
            $captured,
        );
        $resource = new SignatureResource($http);

        $resource->builder()
            ->title('Multi-doc')
            ->document('PDF', 'main.pdf')
            ->attachments([
                ['document' => base64_encode('A'), 'document_name' => 'a.pdf'],
                ['document' => base64_encode('B'), 'document_name' => 'b.pdf'],
                ['document' => base64_encode('C'), 'document_name' => 'c.pdf'],
            ])
            ->addEmailSigner('Jean', 'Dupont', 'jean@example.com')
            ->create();

        $body = $this->lastRequestBody($captured);

        $this->assertCount(3, $body['attachments']);
        $this->assertSame(['a.pdf', 'b.pdf', 'c.pdf'], array_column($body['attachments'], 'document_name'));
    }

    #[Test]
    public function payload_omits_attachments_when_not_set_backward_compat(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(201, ['Content-Type' => 'application/json'], json_encode($this->signatureFixture()))],
            $captured,
        );
        $resource = new SignatureResource($http);

        // Pas d'attachments : payload v2.15 inchange.
        $resource->builder()
            ->title('Mono-doc')
            ->document('PDF', 'doc.pdf')
            ->addEmailSigner('Jean', 'Dupont', 'jean@example.com')
            ->create();

        $body = $this->lastRequestBody($captured);
        $this->assertArrayNotHasKey('attachments', $body);
    }

    #[Test]
    public function builder_rejects_more_than_10_attachments_via_addAttachment(): void
    {
        $captured = [];
        $http = $this->buildHttp([], $captured);
        $resource = new SignatureResource($http);
        $builder = $resource->builder();

        for ($i = 0; $i < 10; $i++) {
            $builder->addAttachment(base64_encode("doc-{$i}"), "doc-{$i}.pdf");
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('maximum of 10 attachments');

        $builder->addAttachment(base64_encode('overflow'), 'overflow.pdf');
    }

    #[Test]
    public function builder_rejects_more_than_10_attachments_via_setter(): void
    {
        $captured = [];
        $http = $this->buildHttp([], $captured);
        $resource = new SignatureResource($http);

        $list = [];
        for ($i = 0; $i < 11; $i++) {
            $list[] = ['document' => base64_encode("doc-{$i}"), 'document_name' => "doc-{$i}.pdf"];
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('maximum of 10 attachments');

        $resource->builder()->attachments($list);
    }

    /* =========================================================================
     | document_index on signature_positions
     | ======================================================================= */

    #[Test]
    public function builder_serializes_document_index_on_signature_position(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(201, ['Content-Type' => 'application/json'], json_encode($this->signatureFixture()))],
            $captured,
        );
        $resource = new SignatureResource($http);

        $resource->builder()
            ->title('Multi-doc positions')
            ->document('PDF', 'main.pdf')
            ->addAttachment(base64_encode('CGV'), 'cgv.pdf')
            ->addEmailSigner('Jean', 'Dupont', 'jean@example.com')
            ->addSignaturePosition(page: 2, x: 70, y: 80, documentIndex: 0)
            ->addSignaturePosition(page: 1, x: 70, y: 80, documentIndex: 1)
            ->addSignaturePosition(page: 1, x: 70, y: 80) // sans index
            ->create();

        $body = $this->lastRequestBody($captured);
        $positions = $body['signature_positions'];

        $this->assertCount(3, $positions);
        $this->assertSame(0, $positions[0]['document_index']);
        $this->assertSame(1, $positions[1]['document_index']);
        $this->assertArrayNotHasKey('document_index', $positions[2]);
    }

    #[Test]
    public function addSignaturePosition_rejects_invalid_document_index(): void
    {
        $captured = [];
        $http = $this->buildHttp([], $captured);
        $resource = new SignatureResource($http);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid documentIndex 11');

        $resource->builder()->addSignaturePosition(page: 1, x: 50, y: 50, documentIndex: 11);
    }

    /* =========================================================================
     | document_index on BlockPosition (mention / date)
     | ======================================================================= */

    #[Test]
    public function block_position_serializes_document_index(): void
    {
        $pos = new BlockPosition(x: 10, y: 80, page: 1, documentIndex: 2);

        $arr = $pos->toArray();
        $this->assertSame(2, $arr['document_index']);
    }

    #[Test]
    public function block_position_omits_document_index_when_null(): void
    {
        $pos = new BlockPosition(x: 10, y: 80, page: 1);

        $arr = $pos->toArray();
        $this->assertArrayNotHasKey('document_index', $arr);
    }

    #[Test]
    public function block_position_round_trips_with_document_index(): void
    {
        $pos = new BlockPosition(x: 10, y: 80, page: 1, documentIndex: 3);

        $rebuilt = BlockPosition::fromArray($pos->toArray());
        $this->assertSame(3, $rebuilt->documentIndex);
    }

    #[Test]
    public function block_position_rejects_invalid_document_index(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid documentIndex 15');

        new BlockPosition(x: 10, y: 80, page: 1, documentIndex: 15);
    }

    #[Test]
    public function block_position_rejects_negative_document_index(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid documentIndex -1');

        new BlockPosition(x: 10, y: 80, page: 1, documentIndex: -1);
    }

    #[Test]
    public function mention_with_document_index_serializes_correctly(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(201, ['Content-Type' => 'application/json'], json_encode($this->signatureFixture()))],
            $captured,
        );
        $resource = new SignatureResource($http);

        $mention = new Mention(
            label: 'Lu et approuve',
            signerIndex: 0,
            position: new BlockPosition(x: 10, y: 80, page: 1, documentIndex: 1),
        );

        $resource->builder()
            ->title('Multi-doc mention')
            ->document('PDF', 'main.pdf')
            ->addAttachment(base64_encode('CGV'), 'cgv.pdf')
            ->addEmailSigner('Jean', 'Dupont', 'jean@example.com')
            ->addMention($mention)
            ->create();

        $body = $this->lastRequestBody($captured);
        $this->assertSame(1, $body['mentions'][0]['position']['document_index']);
    }

    /* =========================================================================
     | document_index on InitialsPosition
     | ======================================================================= */

    #[Test]
    public function initials_position_serializes_document_index(): void
    {
        $pos = new InitialsPosition(page: 1, x: 90, y: 90, documentIndex: 2);

        $arr = $pos->toArray();
        $this->assertSame(2, $arr['document_index']);
    }

    #[Test]
    public function initials_position_omits_document_index_when_null(): void
    {
        $pos = new InitialsPosition(page: 1, x: 90, y: 90);

        $arr = $pos->toArray();
        $this->assertArrayNotHasKey('document_index', $arr);
    }

    #[Test]
    public function initials_position_round_trips_with_document_index(): void
    {
        $pos = new InitialsPosition(page: 1, x: 90, y: 90, documentIndex: 4);

        $rebuilt = InitialsPosition::fromArray($pos->toArray());
        $this->assertSame(4, $rebuilt->documentIndex);
    }

    #[Test]
    public function initials_position_rejects_invalid_document_index(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid documentIndex 99');

        new InitialsPosition(page: 1, x: 90, y: 90, documentIndex: 99);
    }

    #[Test]
    public function initials_block_with_per_page_document_index_serializes(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(201, ['Content-Type' => 'application/json'], json_encode($this->signatureFixture()))],
            $captured,
        );
        $resource = new SignatureResource($http);

        $block = InitialsBlock::withPositions(
            positions: [
                new InitialsPosition(page: 1, x: 90, y: 90, documentIndex: 0),
                new InitialsPosition(page: 1, x: 90, y: 90, documentIndex: 1),
                new InitialsPosition(page: 2, x: 90, y: 90, documentIndex: 1),
            ],
        );

        $resource->builder()
            ->title('Multi-doc paraphe')
            ->document('PDF', 'main.pdf')
            ->addAttachment(base64_encode('CGV'), 'cgv.pdf')
            ->addEmailSigner('Jean', 'Dupont', 'jean@example.com')
            ->initialsBlock($block)
            ->create();

        $body = $this->lastRequestBody($captured);
        $positions = $body['initials_block']['positions'];

        $this->assertCount(3, $positions);
        $this->assertSame(0, $positions[0]['document_index']);
        $this->assertSame(1, $positions[1]['document_index']);
        $this->assertSame(1, $positions[2]['document_index']);
    }

    /* =========================================================================
     | addAttachmentFromFile (helper)
     | ======================================================================= */

    #[Test]
    public function addAttachmentFromFile_reads_and_base64_encodes_file(): void
    {
        $tmpPath = sys_get_temp_dir() . '/scell-sdk-test-attachment-' . uniqid() . '.pdf';
        file_put_contents($tmpPath, 'FAKE_PDF_BYTES');

        $captured = [];
        $http = $this->buildHttp(
            [new Response(201, ['Content-Type' => 'application/json'], json_encode($this->signatureFixture()))],
            $captured,
        );
        $resource = new SignatureResource($http);

        try {
            $resource->builder()
                ->title('Test')
                ->document('PDF', 'main.pdf')
                ->addAttachmentFromFile($tmpPath)
                ->addEmailSigner('Jean', 'Dupont', 'jean@example.com')
                ->create();

            $body = $this->lastRequestBody($captured);
            $this->assertCount(1, $body['attachments']);
            $this->assertSame(base64_encode('FAKE_PDF_BYTES'), $body['attachments'][0]['document']);
            $this->assertSame(basename($tmpPath), $body['attachments'][0]['document_name']);
        } finally {
            @unlink($tmpPath);
        }
    }

    #[Test]
    public function addAttachmentFromFile_throws_on_missing_file(): void
    {
        $captured = [];
        $http = $this->buildHttp([], $captured);
        $resource = new SignatureResource($http);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Fichier non trouve');

        $resource->builder()->addAttachmentFromFile('/nonexistent/path/to/file.pdf');
    }
}
