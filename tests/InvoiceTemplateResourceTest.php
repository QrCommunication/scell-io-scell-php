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
use Scell\Sdk\DTOs\InvoiceTemplate;
use Scell\Sdk\Http\HttpClient;
use Scell\Sdk\Resources\InvoiceTemplateResource;

/**
 * Couvre `InvoiceTemplateResource::deriveColorsFromEmailLogo()` (SDK 3.4.0) —
 * endpoint backend `POST /invoice-templates/derive-colors-from-email-logo` —
 * et le nouveau champ `InvoiceTemplate::$isEnabled`.
 */
class InvoiceTemplateResourceTest extends TestCase
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
     * @return array<string, mixed>
     */
    private function makeTemplatePayload(): array
    {
        return [
            'id' => 'tpl-uuid-01',
            'scope' => 'tenant',
            'tenant_id' => 'tenant-uuid-01',
            'sub_tenant_id' => null,
            'name' => 'Template par defaut',
            'description' => null,
            'is_default' => true,
            'is_available_to_subtenants' => false,
            'logo_url' => 'https://cdn.scell.io/logos/tenant.png',
            'logo_position' => 'top-left',
            'primary_color' => '#0F4C81',
            'accent_color' => '#E8B647',
            'text_color' => null,
            'background_color' => null,
            'header_text' => null,
            'footer_text' => null,
            'custom_mentions' => null,
            'advanced_options' => null,
            'metadata' => null,
            'is_enabled' => true,
            'created_at' => '2026-06-11T10:00:00Z',
            'updated_at' => '2026-06-11T10:00:00Z',
        ];
    }

    // -------------------------------------------------------------------------
    // deriveColorsFromEmailLogo()
    // -------------------------------------------------------------------------

    #[Test]
    public function derive_colors_from_email_logo_posts_without_body_and_returns_template(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => $this->makeTemplatePayload(),
            ]))],
            $captured,
        );

        $template = (new InvoiceTemplateResource($http))->deriveColorsFromEmailLogo();

        $this->assertCount(1, $captured);
        $this->assertSame('POST', $captured[0]->getMethod());
        $this->assertSame(
            'https://api.scell.io/api/v1/invoice-templates/derive-colors-from-email-logo',
            (string) $captured[0]->getUri()->withQuery(''),
        );

        // POST sans body metier : payload JSON vide
        $body = json_decode((string) $captured[0]->getBody(), true);
        $this->assertSame([], $body ?? []);

        $this->assertInstanceOf(InvoiceTemplate::class, $template);
        $this->assertSame('#0F4C81', $template->primaryColor);
        $this->assertSame('#E8B647', $template->accentColor);
        $this->assertTrue($template->isDefault);
    }

    // -------------------------------------------------------------------------
    // InvoiceTemplate::$isEnabled
    // -------------------------------------------------------------------------

    #[Test]
    public function invoice_template_dto_hydrates_is_enabled_false(): void
    {
        $template = InvoiceTemplate::fromArray(array_merge($this->makeTemplatePayload(), [
            'is_enabled' => false,
        ]));

        $this->assertFalse($template->isEnabled);
    }

    #[Test]
    public function invoice_template_dto_is_enabled_defaults_to_true_on_legacy_payloads(): void
    {
        $legacy = $this->makeTemplatePayload();
        unset($legacy['is_enabled']);

        $template = InvoiceTemplate::fromArray($legacy);

        $this->assertTrue($template->isEnabled);
    }
}
