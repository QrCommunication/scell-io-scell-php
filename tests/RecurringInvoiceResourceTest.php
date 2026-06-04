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
use Scell\Sdk\DTOs\Address;
use Scell\Sdk\DTOs\PaginatedResult;
use Scell\Sdk\DTOs\RecurringInvoiceOccurrence;
use Scell\Sdk\DTOs\RecurringInvoiceProfile;
use Scell\Sdk\Http\HttpClient;
use Scell\Sdk\Resources\RecurringInvoiceResource;
use Scell\Sdk\ScellApiClient;
use Scell\Sdk\ScellClient;

/**
 * Tests pour RecurringInvoiceResource (SDK v2.34.0).
 *
 * Couvre le CRUD complet (list/get/create/update/delete), les occurrences,
 * les actions de cycle de vie (pause/activate/cancel/run-now) + le mapping
 * des DTOs RecurringInvoiceProfile et RecurringInvoiceOccurrence, et le
 * cablage des accessors sur ScellClient + ScellApiClient.
 */
class RecurringInvoiceResourceTest extends TestCase
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
     * @return array<string, mixed>
     */
    private function profilePayload(string $id = 'profile-uuid-1'): array
    {
        return [
            'id'                 => $id,
            'title'              => 'Abonnement mensuel SaaS',
            'status'             => 'active',
            'emission_mode'      => 'auto_send',
            'environment'        => 'sandbox',
            'tenant_id'          => 'tenant-uuid',
            'sub_tenant_id'      => null,
            'company_id'         => 'company-uuid',
            'buyer_id'           => 'buyer-uuid',
            'buyer_name'         => 'Client SA',
            'currency'           => 'EUR',
            'output_format'      => 'facturx',
            'payment_terms'      => 'Paiement a 30 jours',
            'recurrence'         => [
                'interval_unit'  => 'month',
                'interval_count' => 1,
                'day_of_month'   => 1,
                'day_of_week'    => null,
                'human'          => 'Tous les 1er du mois',
            ],
            'start_date'         => '2026-07-01',
            'end_mode'           => 'after_occurrences',
            'end_date'           => null,
            'max_occurrences'    => 12,
            'notify_before_days' => 3,
            'next_run_at'        => '2026-07-01T08:00:00+00:00',
            'occurrences_count'  => 2,
            'last_emitted_on'    => '2026-08-01',
            'lines'              => [
                [
                    'description' => 'Licence SaaS',
                    'quantity'    => 1,
                    'unit_price'  => 49.0,
                    'tax_rate'    => 20.0,
                    'total_ht'    => 49.0,
                    'total_tax'   => 9.8,
                    'total_ttc'   => 58.8,
                ],
            ],
            'totals'             => [
                'total_ht'  => 49.0,
                'total_tax' => 9.8,
                'total_ttc' => 58.8,
            ],
            'created_at'         => '2026-06-15T10:00:00+00:00',
            'updated_at'         => '2026-06-15T10:00:00+00:00',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function occurrencePayload(string $id = 'occ-uuid-1'): array
    {
        return [
            'id'                    => $id,
            'recurring_profile_id'  => 'profile-uuid-1',
            'occurrence_number'     => 2,
            'occurrence_date'       => '2026-08-01',
            'status'                => 'emitted',
            'invoice_id'            => 'invoice-uuid',
            'invoice_number'        => 'FACT-2026-0042',
            'attempts'              => 1,
            'last_error'            => null,
            'emitted_at'            => '2026-08-01T08:00:05+00:00',
            'failed_at'             => null,
            'created_at'            => '2026-08-01T08:00:00+00:00',
        ];
    }

    // -------------------------------------------------------------------------
    // list()
    // -------------------------------------------------------------------------

    #[Test]
    public function list_calls_recurring_invoices_endpoint_with_filters_and_returns_paginated_profiles(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => [
                    $this->profilePayload('profile-1'),
                    $this->profilePayload('profile-2'),
                ],
                'meta' => [
                    'current_page' => 1,
                    'last_page'    => 1,
                    'per_page'     => 25,
                    'total'        => 2,
                ],
            ]))],
            $captured,
        );

        $result = (new RecurringInvoiceResource($http))->list([
            'status'        => 'active',
            'sub_tenant_id' => 'sub-uuid',
            'per_page'      => 25,
        ]);

        $this->assertInstanceOf(PaginatedResult::class, $result);
        $this->assertCount(2, $result->data);
        $this->assertContainsOnlyInstancesOf(RecurringInvoiceProfile::class, $result->data);
        $this->assertSame('profile-1', $result->data[0]->id);
        $this->assertSame(2, $result->total);

        $this->assertCount(1, $captured);
        $this->assertSame('GET', $captured[0]->getMethod());
        $uri = (string) $captured[0]->getUri();
        $this->assertStringContainsString('/recurring-invoices', $uri);
        $this->assertStringContainsString('status=active', $uri);
        $this->assertStringContainsString('sub_tenant_id=sub-uuid', $uri);
        $this->assertStringContainsString('per_page=25', $uri);
    }

    // -------------------------------------------------------------------------
    // get()
    // -------------------------------------------------------------------------

    #[Test]
    public function get_calls_recurring_invoices_id_endpoint_and_returns_dto(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => $this->profilePayload('profile-42'),
            ]))],
            $captured,
        );

        $profile = (new RecurringInvoiceResource($http))->get('profile-42');

        $this->assertInstanceOf(RecurringInvoiceProfile::class, $profile);
        $this->assertSame('profile-42', $profile->id);
        $this->assertSame('Abonnement mensuel SaaS', $profile->title);

        $this->assertSame('GET', $captured[0]->getMethod());
        $this->assertStringContainsString('/recurring-invoices/profile-42', (string) $captured[0]->getUri());
    }

    // -------------------------------------------------------------------------
    // create()
    // -------------------------------------------------------------------------

    #[Test]
    public function create_posts_payload_and_normalizes_address_dtos(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(201, ['Content-Type' => 'application/json'], json_encode([
                'data' => $this->profilePayload('profile-new'),
            ]))],
            $captured,
        );

        $profile = (new RecurringInvoiceResource($http))->create([
            'title'           => 'Abonnement mensuel SaaS',
            'buyer_name'      => 'Client SA',
            'buyer_country'   => 'FR',
            'buyer_address'   => new Address(
                line1: '2 Avenue Client',
                postalCode: '69001',
                city: 'Lyon',
                country: 'FR',
            ),
            'buyer_shipping_address' => new Address(
                line1: 'Entrepot Lyon',
                postalCode: '69100',
                city: 'Villeurbanne',
                country: 'FR',
            ),
            'lines'           => [
                ['description' => 'Licence', 'quantity' => 1, 'unit_price' => 49.0, 'vat_rate' => 20.0],
            ],
            'recurrence'      => ['interval_unit' => 'month', 'interval_count' => 1, 'day_of_month' => 1],
            'start_date'      => '2026-07-01',
            'emission_mode'   => 'auto_send',
        ]);

        $this->assertInstanceOf(RecurringInvoiceProfile::class, $profile);
        $this->assertSame('profile-new', $profile->id);

        $this->assertSame('POST', $captured[0]->getMethod());
        $this->assertStringContainsString('/recurring-invoices', (string) $captured[0]->getUri());

        $body = json_decode((string) $captured[0]->getBody(), true);
        // Les Address DTO doivent etre normalises en tableaux
        $this->assertIsArray($body['buyer_address']);
        $this->assertSame('2 Avenue Client', $body['buyer_address']['line1']);
        $this->assertSame('69001', $body['buyer_address']['postal_code']);
        $this->assertIsArray($body['buyer_shipping_address']);
        $this->assertSame('Entrepot Lyon', $body['buyer_shipping_address']['line1']);
        // La recurrence et les lignes passent telles quelles
        $this->assertSame('month', $body['recurrence']['interval_unit']);
        $this->assertSame('auto_send', $body['emission_mode']);
    }

    // -------------------------------------------------------------------------
    // update()
    // -------------------------------------------------------------------------

    #[Test]
    public function update_puts_profile_and_returns_dto(): void
    {
        $captured = [];
        $payload = $this->profilePayload('profile-7');
        $payload['title'] = 'Abonnement annuel';
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => $payload,
            ]))],
            $captured,
        );

        $profile = (new RecurringInvoiceResource($http))->update('profile-7', [
            'title' => 'Abonnement annuel',
        ]);

        $this->assertSame('Abonnement annuel', $profile->title);

        $this->assertSame('PUT', $captured[0]->getMethod());
        $this->assertStringContainsString('/recurring-invoices/profile-7', (string) $captured[0]->getUri());

        $body = json_decode((string) $captured[0]->getBody(), true);
        $this->assertSame('Abonnement annuel', $body['title']);
    }

    // -------------------------------------------------------------------------
    // delete()
    // -------------------------------------------------------------------------

    #[Test]
    public function delete_calls_delete_endpoint(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(204, [], '')],
            $captured,
        );

        (new RecurringInvoiceResource($http))->delete('profile-9');

        $this->assertSame('DELETE', $captured[0]->getMethod());
        $this->assertStringContainsString('/recurring-invoices/profile-9', (string) $captured[0]->getUri());
    }

    // -------------------------------------------------------------------------
    // occurrences()
    // -------------------------------------------------------------------------

    #[Test]
    public function occurrences_calls_nested_endpoint_and_returns_paginated_occurrences(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => [
                    $this->occurrencePayload('occ-1'),
                    $this->occurrencePayload('occ-2'),
                ],
                'meta' => [
                    'current_page' => 1,
                    'last_page'    => 1,
                    'per_page'     => 50,
                    'total'        => 2,
                ],
            ]))],
            $captured,
        );

        $result = (new RecurringInvoiceResource($http))->occurrences('profile-1', ['per_page' => 50]);

        $this->assertInstanceOf(PaginatedResult::class, $result);
        $this->assertCount(2, $result->data);
        $this->assertContainsOnlyInstancesOf(RecurringInvoiceOccurrence::class, $result->data);
        $this->assertSame('occ-1', $result->data[0]->id);

        $this->assertSame('GET', $captured[0]->getMethod());
        $uri = (string) $captured[0]->getUri();
        $this->assertStringContainsString('/recurring-invoices/profile-1/occurrences', $uri);
        $this->assertStringContainsString('per_page=50', $uri);
    }

    // -------------------------------------------------------------------------
    // pause / activate / cancel
    // -------------------------------------------------------------------------

    #[Test]
    public function pause_posts_to_pause_action_and_returns_dto(): void
    {
        $captured = [];
        $payload = $this->profilePayload('profile-p');
        $payload['status'] = 'paused';
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode(['data' => $payload]))],
            $captured,
        );

        $profile = (new RecurringInvoiceResource($http))->pause('profile-p');

        $this->assertTrue($profile->isPaused());
        $this->assertSame('POST', $captured[0]->getMethod());
        $this->assertStringContainsString('/recurring-invoices/profile-p/pause', (string) $captured[0]->getUri());
    }

    #[Test]
    public function activate_posts_to_activate_action_and_returns_dto(): void
    {
        $captured = [];
        $payload = $this->profilePayload('profile-a');
        $payload['status'] = 'active';
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode(['data' => $payload]))],
            $captured,
        );

        $profile = (new RecurringInvoiceResource($http))->activate('profile-a');

        $this->assertTrue($profile->isActive());
        $this->assertSame('POST', $captured[0]->getMethod());
        $this->assertStringContainsString('/recurring-invoices/profile-a/activate', (string) $captured[0]->getUri());
    }

    #[Test]
    public function cancel_posts_to_cancel_action_and_returns_dto(): void
    {
        $captured = [];
        $payload = $this->profilePayload('profile-c');
        $payload['status'] = 'cancelled';
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode(['data' => $payload]))],
            $captured,
        );

        $profile = (new RecurringInvoiceResource($http))->cancel('profile-c');

        $this->assertTrue($profile->isCancelled());
        $this->assertSame('POST', $captured[0]->getMethod());
        $this->assertStringContainsString('/recurring-invoices/profile-c/cancel', (string) $captured[0]->getUri());
    }

    // -------------------------------------------------------------------------
    // runNow()
    // -------------------------------------------------------------------------

    #[Test]
    public function run_now_posts_to_run_now_action_and_returns_message(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(202, ['Content-Type' => 'application/json'], json_encode([
                'message' => 'Emission planifiee',
            ]))],
            $captured,
        );

        $result = (new RecurringInvoiceResource($http))->runNow('profile-r');

        $this->assertSame('Emission planifiee', $result['message']);
        $this->assertSame('POST', $captured[0]->getMethod());
        $this->assertStringContainsString('/recurring-invoices/profile-r/run-now', (string) $captured[0]->getUri());
    }

    // -------------------------------------------------------------------------
    // DTO mapping
    // -------------------------------------------------------------------------

    #[Test]
    public function profile_dto_maps_all_fields_from_api_payload(): void
    {
        $profile = RecurringInvoiceProfile::fromArray($this->profilePayload('profile-dto'));

        $this->assertSame('profile-dto', $profile->id);
        $this->assertSame('Abonnement mensuel SaaS', $profile->title);
        $this->assertSame('active', $profile->status);
        $this->assertSame('auto_send', $profile->emissionMode);
        $this->assertSame('sandbox', $profile->environment);
        $this->assertSame('tenant-uuid', $profile->tenantId);
        $this->assertNull($profile->subTenantId);
        $this->assertSame('company-uuid', $profile->companyId);
        $this->assertSame('buyer-uuid', $profile->buyerId);
        $this->assertSame('Client SA', $profile->buyerName);
        $this->assertSame('EUR', $profile->currency);
        $this->assertSame('facturx', $profile->outputFormat);
        $this->assertSame('Paiement a 30 jours', $profile->paymentTerms);
        $this->assertSame('month', $profile->recurrence['interval_unit']);
        $this->assertSame(1, $profile->recurrence['interval_count']);
        $this->assertSame(1, $profile->recurrence['day_of_month']);
        $this->assertSame('Tous les 1er du mois', $profile->recurrence['human']);
        $this->assertSame('2026-07-01', $profile->startDate->format('Y-m-d'));
        $this->assertSame('after_occurrences', $profile->endMode);
        $this->assertNull($profile->endDate);
        $this->assertSame(12, $profile->maxOccurrences);
        $this->assertSame(3, $profile->notifyBeforeDays);
        $this->assertNotNull($profile->nextRunAt);
        $this->assertSame(2, $profile->occurrencesCount);
        $this->assertSame('2026-08-01', $profile->lastEmittedOn->format('Y-m-d'));
        $this->assertCount(1, $profile->lines);
        $this->assertSame('Licence SaaS', $profile->lines[0]->description);
        $this->assertSame(49.0, $profile->totals['total_ht']);
        $this->assertSame(58.8, $profile->totals['total_ttc']);
        $this->assertNotNull($profile->createdAt);
        $this->assertNotNull($profile->updatedAt);

        // Helpers
        $this->assertTrue($profile->isActive());
        $this->assertFalse($profile->isPaused());
        $this->assertTrue($profile->isAutoSend());
        $this->assertTrue($profile->isSandbox());
    }

    #[Test]
    public function profile_dto_derives_defaults_for_minimal_payload(): void
    {
        $profile = RecurringInvoiceProfile::fromArray([
            'id'         => 'profile-min',
            'title'      => 'Minimal',
            'tenant_id'  => 'tenant-uuid',
            'start_date' => '2026-07-01',
        ]);

        $this->assertSame('profile-min', $profile->id);
        $this->assertSame('active', $profile->status);
        $this->assertSame('draft', $profile->emissionMode);
        $this->assertSame('never', $profile->endMode);
        $this->assertSame('EUR', $profile->currency);
        $this->assertSame('month', $profile->recurrence['interval_unit']);
        $this->assertSame(1, $profile->recurrence['interval_count']);
        $this->assertNull($profile->recurrence['day_of_month']);
        $this->assertSame(0.0, $profile->totals['total_ht']);
        $this->assertSame([], $profile->lines);
        $this->assertNull($profile->buyerId);
        $this->assertNull($profile->nextRunAt);
        $this->assertSame(0, $profile->occurrencesCount);
        $this->assertNull($profile->lastEmittedOn);
        $this->assertNull($profile->createdAt);
    }

    #[Test]
    public function occurrence_dto_maps_all_fields_from_api_payload(): void
    {
        $occurrence = RecurringInvoiceOccurrence::fromArray($this->occurrencePayload('occ-dto'));

        $this->assertSame('occ-dto', $occurrence->id);
        $this->assertSame('profile-uuid-1', $occurrence->recurringProfileId);
        $this->assertSame(2, $occurrence->occurrenceNumber);
        $this->assertSame('2026-08-01', $occurrence->occurrenceDate->format('Y-m-d'));
        $this->assertSame('emitted', $occurrence->status);
        $this->assertSame('invoice-uuid', $occurrence->invoiceId);
        $this->assertSame('FACT-2026-0042', $occurrence->invoiceNumber);
        $this->assertSame(1, $occurrence->attempts);
        $this->assertNull($occurrence->lastError);
        $this->assertNotNull($occurrence->emittedAt);
        $this->assertNull($occurrence->failedAt);
        $this->assertNotNull($occurrence->createdAt);

        // Helpers
        $this->assertTrue($occurrence->isEmitted());
        $this->assertFalse($occurrence->isPending());
        $this->assertFalse($occurrence->isFailed());
        $this->assertFalse($occurrence->isSkipped());
    }

    #[Test]
    public function occurrence_dto_handles_failed_payload(): void
    {
        $occurrence = RecurringInvoiceOccurrence::fromArray([
            'id'                   => 'occ-fail',
            'recurring_profile_id' => 'profile-uuid-1',
            'occurrence_number'    => 3,
            'occurrence_date'      => '2026-09-01',
            'status'               => 'failed',
            'attempts'             => 2,
            'last_error'           => 'KYB_REQUIRED',
            'failed_at'            => '2026-09-01T08:00:10+00:00',
        ]);

        $this->assertTrue($occurrence->isFailed());
        $this->assertSame('KYB_REQUIRED', $occurrence->lastError);
        $this->assertSame(2, $occurrence->attempts);
        $this->assertNull($occurrence->invoiceId);
        $this->assertNotNull($occurrence->failedAt);
    }

    // -------------------------------------------------------------------------
    // Client wiring (STEP 4)
    // -------------------------------------------------------------------------

    #[Test]
    public function scell_client_exposes_recurring_invoices_accessor(): void
    {
        $client = new ScellClient('bearer-token');

        $resource = $client->recurringInvoices();
        $this->assertInstanceOf(RecurringInvoiceResource::class, $resource);
        // Lazy singleton : meme instance a chaque appel
        $this->assertSame($resource, $client->recurringInvoices());
    }

    #[Test]
    public function scell_api_client_exposes_recurring_invoices_accessor(): void
    {
        $client = ScellApiClient::withApiKey('sk_test_xxx');

        $resource = $client->recurringInvoices();
        $this->assertInstanceOf(RecurringInvoiceResource::class, $resource);
        $this->assertSame($resource, $client->recurringInvoices());
    }
}
