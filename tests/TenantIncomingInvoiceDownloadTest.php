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
use Scell\Sdk\Http\HttpClient;
use Scell\Sdk\Resources\TenantIncomingInvoiceResource;

/**
 * Couvre `TenantIncomingInvoiceResource::download()` (SDK 3.2.0) —
 * endpoint backend `GET /tenant/invoices/incoming/{id}/download`.
 */
class TenantIncomingInvoiceDownloadTest extends TestCase
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
    public function download_returns_raw_pdf_body_with_default_format(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/pdf'], '%PDF-1.4 binary content')],
            $captured,
        );

        $pdf = (new TenantIncomingInvoiceResource($http))->download('invoice-uuid-99');

        $this->assertCount(1, $captured);
        $this->assertSame('GET', $captured[0]->getMethod());
        $this->assertSame(
            'https://api.scell.io/api/v1/tenant/invoices/incoming/invoice-uuid-99/download',
            (string) $captured[0]->getUri()->withQuery(''),
        );
        $this->assertSame('format=pdf', $captured[0]->getUri()->getQuery());
        $this->assertSame('%PDF-1.4 binary content', $pdf);
    }

    #[Test]
    public function download_passes_xml_format_as_query_param(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/xml'], '<?xml version="1.0"?><invoice/>')],
            $captured,
        );

        $xml = (new TenantIncomingInvoiceResource($http))->download('invoice-uuid-99', 'xml');

        $this->assertSame('format=xml', $captured[0]->getUri()->getQuery());
        $this->assertStringContainsString('<invoice/>', $xml);
    }
}
