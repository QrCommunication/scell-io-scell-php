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
use Scell\Sdk\Resources\InvoiceMentionsResource;
use Scell\Sdk\Resources\InvoiceResource;
use Scell\Sdk\Resources\InvoiceTemplateResource;

/**
 * Couvre les gaps de couverture REST comblés en SDK 3.5.0 :
 * - invoice-templates : deriveColorsFromInvoiceLogo + preview (HTML/PDF)
 * - invoices : deposit-groups (liste + détail)
 * - invoice-mentions : assistant + preview
 */
class SdkCoverageGaps35Test extends TestCase
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

    // -------------------------------------------------------------------------
    // InvoiceTemplateResource::deriveColorsFromInvoiceLogo()
    // -------------------------------------------------------------------------

    #[Test]
    public function derive_colors_from_invoice_logo_posts_and_returns_palette(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => ['primary_color' => '#0F4C81', 'accent_color' => '#E8B647'],
            ]))],
            $captured,
        );

        $palette = (new InvoiceTemplateResource($http))->deriveColorsFromInvoiceLogo();

        $this->assertCount(1, $captured);
        $this->assertSame('POST', $captured[0]->getMethod());
        $this->assertSame(
            'https://api.scell.io/api/v1/invoice-templates/derive-colors-from-invoice-logo',
            (string) $captured[0]->getUri()->withQuery(''),
        );
        $this->assertSame('#0F4C81', $palette['primary_color']);
        $this->assertSame('#E8B647', $palette['accent_color']);
    }

    // -------------------------------------------------------------------------
    // InvoiceTemplateResource::preview()
    // -------------------------------------------------------------------------

    #[Test]
    public function preview_invoice_template_returns_raw_html_with_query_params(): void
    {
        $captured = [];
        $html = '<!DOCTYPE html><html><body>apercu facture</body></html>';
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], $html)],
            $captured,
        );

        $result = (new InvoiceTemplateResource($http))->preview([
            'primary_color' => '#0F4C81',
            'logo_url' => 'https://cdn.client.com/logo.svg',
        ]);

        $this->assertSame($html, $result);
        $this->assertSame('GET', $captured[0]->getMethod());
        $this->assertStringStartsWith(
            'https://api.scell.io/api/v1/invoice-templates/preview',
            (string) $captured[0]->getUri(),
        );
        parse_str($captured[0]->getUri()->getQuery(), $query);
        $this->assertSame('#0F4C81', $query['primary_color']);
        $this->assertSame('https://cdn.client.com/logo.svg', $query['logo_url']);
    }

    #[Test]
    public function preview_invoice_template_returns_raw_pdf_bytes(): void
    {
        $captured = [];
        $pdf = "%PDF-1.4\n%binarydata";
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/pdf'], $pdf)],
            $captured,
        );

        $result = (new InvoiceTemplateResource($http))->preview(['format' => 'pdf']);

        $this->assertSame($pdf, $result);
        parse_str($captured[0]->getUri()->getQuery(), $query);
        $this->assertSame('pdf', $query['format']);
    }

    // -------------------------------------------------------------------------
    // InvoiceResource::depositGroups() / depositGroup()
    // -------------------------------------------------------------------------

    #[Test]
    public function deposit_groups_lists_and_forwards_has_no_balance_filter(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => [
                    [
                        'deposit_group_id' => 'grp-uuid-1',
                        'first_invoice_number' => 'FA-2026-0001',
                        'deposit_reference_text' => 'BC-42',
                        'deposit_total_ht' => 1000.0,
                        'sum_deposits_ht' => 300.0,
                        'remaining_ht' => 700.0,
                        'invoices_count' => 1,
                        'has_balance' => false,
                    ],
                ],
            ]))],
            $captured,
        );

        $groups = (new InvoiceResource($http))->depositGroups(['has_no_balance' => true]);

        $this->assertCount(1, $groups);
        $this->assertSame('grp-uuid-1', $groups[0]['deposit_group_id']);
        $this->assertFalse($groups[0]['has_balance']);

        $this->assertSame('GET', $captured[0]->getMethod());
        $this->assertStringStartsWith(
            'https://api.scell.io/api/v1/invoices/deposit-groups',
            (string) $captured[0]->getUri(),
        );
        parse_str($captured[0]->getUri()->getQuery(), $query);
        $this->assertSame('1', $query['has_no_balance']);
    }

    #[Test]
    public function deposit_group_detail_fetches_by_id(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => [
                    'deposit_group_id' => 'grp-uuid-1',
                    'deposit_total_ht' => 1000.0,
                    'sum_deposits_ht' => 300.5,
                    'remaining_ht' => 699.5,
                    'invoices' => [],
                ],
            ]))],
            $captured,
        );

        $detail = (new InvoiceResource($http))->depositGroup('grp-uuid-1');

        $this->assertSame('grp-uuid-1', $detail['deposit_group_id']);
        $this->assertSame(699.5, $detail['remaining_ht']);
        $this->assertSame('GET', $captured[0]->getMethod());
        $this->assertSame(
            'https://api.scell.io/api/v1/invoices/deposit-groups/grp-uuid-1',
            (string) $captured[0]->getUri()->withQuery(''),
        );
    }

    // -------------------------------------------------------------------------
    // InvoiceMentionsResource::assistant() / preview()
    // -------------------------------------------------------------------------

    #[Test]
    public function invoice_mentions_assistant_posts_params_and_returns_data(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => [
                    'invoice_footer' => 'TVA non applicable, art. 293 B du CGI',
                    'warnings' => [],
                ],
            ]))],
            $captured,
        );

        $data = (new InvoiceMentionsResource($http))->assistant([
            'vat_profile' => 'franchise',
            'clientele' => 'b2c',
            'payment_due_days' => 30,
            'penalty_mode' => 'legal',
        ]);

        $this->assertSame('TVA non applicable, art. 293 B du CGI', $data['invoice_footer']);

        $this->assertSame('POST', $captured[0]->getMethod());
        $this->assertSame(
            'https://api.scell.io/api/v1/invoice-mentions/assistant',
            (string) $captured[0]->getUri()->withQuery(''),
        );
        $body = json_decode((string) $captured[0]->getBody(), true);
        $this->assertSame('franchise', $body['vat_profile']);
        $this->assertSame(30, $body['payment_due_days']);
    }

    #[Test]
    public function invoice_mentions_preview_posts_overrides_and_returns_composed_mentions(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => [
                    'invoice_footer' => 'Ma Société — SIRET 12345678901234',
                    'payment_terms_b2b' => 'Paiement à 30 jours. Pénalités de retard...',
                    'payment_terms_b2c' => 'Paiement à 30 jours.',
                    'vat_franchise_mention' => null,
                ],
            ]))],
            $captured,
        );

        $preview = (new InvoiceMentionsResource($http))->preview([
            'payment_due_days' => 30,
            'name' => 'Ma Société',
        ]);

        $this->assertSame('Ma Société — SIRET 12345678901234', $preview['invoice_footer']);
        $this->assertNull($preview['vat_franchise_mention']);

        $this->assertSame('POST', $captured[0]->getMethod());
        $this->assertSame(
            'https://api.scell.io/api/v1/invoice-mentions/preview',
            (string) $captured[0]->getUri()->withQuery(''),
        );
        $body = json_decode((string) $captured[0]->getBody(), true);
        $this->assertSame('Ma Société', $body['name']);
    }
}
