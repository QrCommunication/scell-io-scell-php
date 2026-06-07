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
use Scell\Sdk\DTOs\Supplier;
use Scell\Sdk\Http\HttpClient;
use Scell\Sdk\Resources\SupplierResource;

/**
 * Tests pour SupplierResource (SDK v3.0.0).
 *
 * Les fournisseurs sont dérivés des factures reçues (source de vérité = facture).
 * Seuls email/phone/notes/metadata sont modifiables via update().
 * create() et delete() ont été supprimés (API 405 — endpoints fermés côté serveur).
 */
class SuppliersResourceTest extends TestCase
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
    private function supplierPayload(string $id = 'supplier-uuid-1'): array
    {
        return [
            'id'              => $id,
            'tenant_id'       => 'tenant-uuid',
            'sub_tenant_id'   => null,
            'name'            => 'Fournitures Pro SARL',
            'is_individual'   => false,
            'billing_address' => [
                'line1'       => '12 rue des Acacias',
                'postal_code' => '69001',
                'city'        => 'Lyon',
                'country'     => 'FR',
            ],
            'country'         => 'FR',
            'siret'           => '12345678901234',
            'vat_number'      => 'FR12345678901',
            'legal_id'        => 'RCS Lyon 123',
            'legal_id_scheme' => 'RCS',
            'email'           => 'contact@fournitures-pro.fr',
            'phone'           => '+33472000000',
            'metadata'        => ['account_ref' => 'F-001'],
            'notes'           => 'Fournisseur principal de papeterie',
            'created_at'      => '2026-05-28T10:00:00+00:00',
            'updated_at'      => '2026-05-28T10:00:00+00:00',
        ];
    }

    // -------------------------------------------------------------------------
    // list()
    // -------------------------------------------------------------------------

    #[Test]
    public function list_calls_suppliers_endpoint_with_filters_and_returns_paginated_dtos(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => [
                    $this->supplierPayload('supplier-1'),
                    $this->supplierPayload('supplier-2'),
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

        $result = (new SupplierResource($http))->list([
            'q'             => 'Pro',
            'is_individual' => false,
            'per_page'      => 25,
        ]);

        $this->assertInstanceOf(PaginatedResult::class, $result);
        $this->assertCount(2, $result->data);
        $this->assertContainsOnlyInstancesOf(Supplier::class, $result->data);
        $this->assertSame('supplier-1', $result->data[0]->id);
        $this->assertSame(2, $result->total);

        $this->assertCount(1, $captured);
        $this->assertSame('GET', $captured[0]->getMethod());
        $uri = (string) $captured[0]->getUri();
        $this->assertStringContainsString('/suppliers', $uri);
        $this->assertStringContainsString('q=Pro', $uri);
        $this->assertStringContainsString('per_page=25', $uri);
    }

    // -------------------------------------------------------------------------
    // get()
    // -------------------------------------------------------------------------

    #[Test]
    public function get_calls_suppliers_id_endpoint_and_returns_dto(): void
    {
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => $this->supplierPayload('supplier-42'),
            ]))],
            $captured,
        );

        $supplier = (new SupplierResource($http))->get('supplier-42');

        $this->assertInstanceOf(Supplier::class, $supplier);
        $this->assertSame('supplier-42', $supplier->id);
        $this->assertSame('Fournitures Pro SARL', $supplier->name);

        $this->assertSame('GET', $captured[0]->getMethod());
        $this->assertStringContainsString('/suppliers/supplier-42', (string) $captured[0]->getUri());
    }

    // -------------------------------------------------------------------------
    // update() — champs d'enrichissement uniquement
    // -------------------------------------------------------------------------

    #[Test]
    public function update_patches_supplier_and_returns_dto(): void
    {
        $captured = [];
        $payload = $this->supplierPayload('supplier-7');
        $payload['notes'] = 'Note mise a jour';
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => $payload,
            ]))],
            $captured,
        );

        $supplier = (new SupplierResource($http))->update('supplier-7', [
            'notes' => 'Note mise a jour',
        ]);

        $this->assertSame('Note mise a jour', $supplier->notes);

        $this->assertSame('PATCH', $captured[0]->getMethod());
        $this->assertStringContainsString('/suppliers/supplier-7', (string) $captured[0]->getUri());

        $body = json_decode((string) $captured[0]->getBody(), true);
        $this->assertSame('Note mise a jour', $body['notes']);
    }

    #[Test]
    public function update_strips_identity_fields_from_patch_payload(): void
    {
        // Garantit que les champs d'identité (dérivés des factures) ne sont
        // jamais transmis à l'API même si le consommateur les passe par erreur.
        $captured = [];
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => $this->supplierPayload('supplier-x'),
            ]))],
            $captured,
        );

        (new SupplierResource($http))->update('supplier-x', [
            // champs d'enrichissement légitimes
            'email'    => 'nouveau@example.com',
            'phone'    => '+33600000001',
            'notes'    => 'Note test',
            'metadata' => ['ref' => 'X'],
            // champs d'identité — ne DOIVENT PAS être transmis
            'name'             => 'Tentative de modification',
            'siret'            => '00000000000000',
            'vat_number'       => 'FR00000000000',
            'country'          => 'DE',
            'billing_address'  => ['line1' => 'hack'],
            'is_individual'    => true,
            'legal_id'         => 'HACK',
            'legal_id_scheme'  => 'HACK',
        ]);

        $body = json_decode((string) $captured[0]->getBody(), true);

        // Seuls les 4 champs d'enrichissement doivent apparaître
        $this->assertSame(['email', 'phone', 'notes', 'metadata'], array_keys($body));
        $this->assertSame('nouveau@example.com', $body['email']);
        $this->assertSame('+33600000001', $body['phone']);

        // Les champs d'identité ne doivent PAS être présents
        $this->assertArrayNotHasKey('name', $body);
        $this->assertArrayNotHasKey('siret', $body);
        $this->assertArrayNotHasKey('vat_number', $body);
        $this->assertArrayNotHasKey('country', $body);
        $this->assertArrayNotHasKey('billing_address', $body);
        $this->assertArrayNotHasKey('is_individual', $body);
        $this->assertArrayNotHasKey('legal_id', $body);
        $this->assertArrayNotHasKey('legal_id_scheme', $body);
    }

    #[Test]
    public function update_sends_only_provided_enrichment_fields(): void
    {
        // Vérifie qu'un PATCH partiel (1 seul champ) n'envoie que ce champ
        $captured = [];
        $payload = $this->supplierPayload('supplier-z');
        $payload['email'] = 'updated@example.com';
        $http = $this->buildHttp(
            [new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => $payload,
            ]))],
            $captured,
        );

        (new SupplierResource($http))->update('supplier-z', ['email' => 'updated@example.com']);

        $body = json_decode((string) $captured[0]->getBody(), true);
        $this->assertSame(['email'], array_keys($body));
        $this->assertSame('updated@example.com', $body['email']);
    }

    // -------------------------------------------------------------------------
    // Garde-fous — create() et delete() sont supprimés (BREAKING v3.0.0)
    // -------------------------------------------------------------------------

    #[Test]
    public function resource_does_not_expose_create_method(): void
    {
        $this->assertFalse(
            method_exists(SupplierResource::class, 'create'),
            'create() a été supprimé en v3.0.0 : les fournisseurs sont dérivés des factures reçues'
        );
    }

    #[Test]
    public function resource_does_not_expose_delete_method(): void
    {
        $this->assertFalse(
            method_exists(SupplierResource::class, 'delete'),
            'delete() a été supprimé en v3.0.0 : endpoint API 405'
        );
    }

    // -------------------------------------------------------------------------
    // DTO mapping
    // -------------------------------------------------------------------------

    #[Test]
    public function supplier_dto_maps_all_fields_from_api_payload(): void
    {
        $supplier = Supplier::fromArray($this->supplierPayload('supplier-dto'));

        $this->assertSame('supplier-dto', $supplier->id);
        $this->assertSame('tenant-uuid', $supplier->tenantId);
        $this->assertNull($supplier->subTenantId);
        $this->assertSame('Fournitures Pro SARL', $supplier->name);
        $this->assertFalse($supplier->isIndividual);
        $this->assertInstanceOf(Address::class, $supplier->billingAddress);
        $this->assertSame('Lyon', $supplier->billingAddress->city);
        $this->assertSame('FR', $supplier->country);
        $this->assertSame('12345678901234', $supplier->siret);
        $this->assertSame('FR12345678901', $supplier->vatNumber);
        $this->assertSame('RCS Lyon 123', $supplier->legalId);
        $this->assertSame('RCS', $supplier->legalIdScheme);
        $this->assertSame('contact@fournitures-pro.fr', $supplier->email);
        $this->assertSame('+33472000000', $supplier->phone);
        $this->assertSame(['account_ref' => 'F-001'], $supplier->metadata);
        $this->assertSame('Fournisseur principal de papeterie', $supplier->notes);
        $this->assertNotNull($supplier->createdAt);
        $this->assertNotNull($supplier->updatedAt);
    }

    #[Test]
    public function supplier_dto_derives_defaults_for_minimal_payload(): void
    {
        $supplier = Supplier::fromArray([
            'id'        => 'supplier-min',
            'tenant_id' => 'tenant-uuid',
            'name'      => 'Particulier Martin',
        ]);

        $this->assertSame('supplier-min', $supplier->id);
        $this->assertFalse($supplier->isIndividual);
        $this->assertSame('FR', $supplier->country);
        $this->assertNull($supplier->siret);
        $this->assertNull($supplier->vatNumber);
        $this->assertNull($supplier->email);
        $this->assertNull($supplier->metadata);
        $this->assertNull($supplier->notes);
        $this->assertNull($supplier->createdAt);
    }

    #[Test]
    public function supplier_dto_has_no_shipping_address_property(): void
    {
        // Garde-fou : un fournisseur ne porte PAS d'adresse de livraison
        // ni d'email de facturation (concepts acheteur uniquement).
        $this->assertFalse(property_exists(Supplier::class, 'shippingAddress'));
        $this->assertFalse(property_exists(Supplier::class, 'billingEmail'));
    }
}
