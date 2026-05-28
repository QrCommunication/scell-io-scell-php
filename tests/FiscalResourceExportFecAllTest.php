<?php

declare(strict_types=1);

namespace Scell\Sdk\Tests;

use DateTimeImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Scell\Sdk\Http\HttpClient;
use Scell\Sdk\Resources\FiscalResource;

/**
 * Couvre la nouvelle methode `FiscalResource::exportFecAll()` (SDK 2.24.0)
 * — endpoint `GET /v1/tenant/fiscal/fec/all` (P0.1 cross sub-tenant FEC export).
 */
class FiscalResourceExportFecAllTest extends TestCase
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

    #[Test]
    public function it_calls_fec_all_endpoint_with_date_strings_returns_array(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'success' => true,
                'message' => 'ZIP FEC genere avec succes.',
                'data' => [
                    'period' => ['start_date' => '2026-01-01', 'end_date' => '2026-12-31'],
                    'format' => 'pipe',
                    'file_path' => '/tmp/fec_all_2026.zip',
                ],
            ]))],
            $captured,
        );

        $result = (new FiscalResource($http))->exportFecAll('2026-01-01', '2026-12-31');

        $this->assertCount(1, $captured);
        $this->assertSame('GET', $captured[0]->getMethod());

        $uri = $captured[0]->getUri();
        $this->assertSame('/api/v1/tenant/fiscal/fec/all', $uri->getPath());

        parse_str($uri->getQuery(), $query);
        $this->assertSame('2026-01-01', $query['start_date']);
        $this->assertSame('2026-12-31', $query['end_date']);
        $this->assertSame('pipe', $query['format']);
        $this->assertArrayNotHasKey('download', $query);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertSame('/tmp/fec_all_2026.zip', $result['data']['file_path']);
    }

    #[Test]
    public function it_accepts_datetime_immutable_for_dates(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode(['data' => []]))],
            $captured,
        );

        (new FiscalResource($http))->exportFecAll(
            new DateTimeImmutable('2026-03-15'),
            new DateTimeImmutable('2026-06-30'),
        );

        parse_str($captured[0]->getUri()->getQuery(), $query);
        $this->assertSame('2026-03-15', $query['start_date']);
        $this->assertSame('2026-06-30', $query['end_date']);
    }

    #[Test]
    public function it_supports_tab_format(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode(['data' => []]))],
            $captured,
        );

        (new FiscalResource($http))->exportFecAll('2026-01-01', '2026-12-31', format: 'tab');

        parse_str($captured[0]->getUri()->getQuery(), $query);
        $this->assertSame('tab', $query['format']);
    }

    #[Test]
    public function it_returns_binary_zip_when_download_is_true(): void
    {
        $captured = [];
        $zipBinary = "PK\x03\x04binary-zip-content-here";

        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/zip'], $zipBinary)],
            $captured,
        );

        $result = (new FiscalResource($http))->exportFecAll(
            '2026-01-01',
            '2026-12-31',
            download: true,
        );

        // Le binaire ZIP est retourne tel quel (string)
        $this->assertIsString($result);
        $this->assertSame($zipBinary, $result);

        // Et le query string contient bien download=1
        parse_str($captured[0]->getUri()->getQuery(), $query);
        $this->assertSame('1', $query['download']);
    }

    #[Test]
    public function it_defaults_format_to_pipe_when_not_specified(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode(['data' => []]))],
            $captured,
        );

        (new FiscalResource($http))->exportFecAll('2026-01-01', '2026-12-31');

        parse_str($captured[0]->getUri()->getQuery(), $query);
        $this->assertSame('pipe', $query['format']);
    }
}
