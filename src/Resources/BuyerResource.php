<?php

declare(strict_types=1);

namespace Scell\Sdk\Resources;

use Scell\Sdk\DTOs\Address;
use Scell\Sdk\DTOs\Buyer;
use Scell\Sdk\DTOs\LineVatContext;
use Scell\Sdk\DTOs\PaginatedResult;
use Scell\Sdk\DTOs\Vat\BuyerContext;
use Scell\Sdk\DTOs\VatResolution;
use Scell\Sdk\Enums\VatCategory;
use Scell\Sdk\Http\HttpClient;

/**
 * Resource pour le registre des acheteurs (Buyers).
 *
 * Le registre est scope strictement par (tenant, sub_tenant) — vous ne
 * verrez que les acheteurs de votre propre scope. Reutilisez les buyers
 * pour emettre des factures sans resaisir l'identite et l'adresse :
 *
 *   $buyer = $client->buyers()->create([...]);
 *   $client->invoices()->create([
 *       'buyer_id' => $buyer->id,
 *       // ... reste de la facture
 *   ]);
 *
 * Modifier un buyer plus tard n'impacte pas les factures deja emises
 * (snapshot immutable cote facture, ISCA fiscal compliance).
 */
class BuyerResource
{
    public function __construct(
        private readonly HttpClient $http,
    ) {}

    /**
     * Liste paginee des acheteurs avec recherche et filtres.
     *
     * @param array{
     *     q?: string,
     *     is_individual?: bool,
     *     per_page?: int,
     *     page?: int,
     * } $params
     * @return PaginatedResult<Buyer>
     */
    public function list(array $params = []): PaginatedResult
    {
        $response = $this->http->get('buyers', $params);
        return PaginatedResult::fromArray($response, fn(array $data) => Buyer::fromArray($data));
    }

    /**
     * Recupere un acheteur par son id.
     */
    public function get(string $id): Buyer
    {
        $response = $this->http->get("buyers/{$id}");
        return Buyer::fromArray($response['data']);
    }

    /**
     * Cree un acheteur.
     *
     * @param array{
     *     name: string,
     *     country: string,
     *     billing_address: Address|array,
     *     is_individual?: bool,
     *     siret?: string,
     *     vat_number?: string,
     *     legal_id?: string,
     *     legal_id_scheme?: string,
     *     email?: string,
     *     phone?: string,
     *     shipping_address?: Address|array,
     *     metadata?: array,
     *     notes?: string,
     * } $data
     */
    public function create(array $data): Buyer
    {
        $payload = $this->normalizePayload($data);
        $response = $this->http->post('buyers', $payload);
        return Buyer::fromArray($response['data']);
    }

    /**
     * Met a jour un acheteur (PATCH partiel).
     *
     * @param array<string, mixed> $data
     */
    public function update(string $id, array $data): Buyer
    {
        $payload = $this->normalizePayload($data);
        $response = $this->http->patch("buyers/{$id}", $payload);
        return Buyer::fromArray($response['data']);
    }

    public function delete(string $id): void
    {
        $this->http->delete("buyers/{$id}");
    }

    /**
     * Resout le contexte TVA cross-border d'une ligne de facture.
     *
     * Le moteur de regles backend determine la categorie TVA applicable
     * (STANDARD, REVERSE_CHARGE, OUT_OF_SCOPE, etc.) en fonction du pays
     * acheteur, de son statut B2B/B2C, de la validite de son numero TVA
     * et des eventuels overrides de lieu de prestation.
     *
     * Deux modes d'appel :
     *
     * **Mode 1 — buyer_id enregistre (string UUID)**
     * ```php
     * $resolution = $client->buyers()->vatContext(
     *     buyerOrInput: '019cb416-b6db-730c-b3a5-f8b7a4512eb1',
     *     line: ['category' => 'STANDARD'],
     * );
     * ```
     *
     * **Mode 2 — buyer inline (array ou BuyerContext)**
     * ```php
     * $resolution = $client->buyers()->vatContext(
     *     buyerOrInput: ['country' => 'DE', 'vat_number' => 'DE123456789'],
     *     line: ['category' => 'STANDARD', 'place_of_supply' => 'FR'],
     * );
     * // ou avec DTO
     * $resolution = $client->buyers()->vatContext(
     *     buyerOrInput: new BuyerContext(country: 'DE', vatNumber: 'DE123456789'),
     *     line: new LineVatContext(category: VatCategory::Standard),
     * );
     * ```
     *
     * @param string|array<string, mixed>|BuyerContext $buyerOrInput
     *   UUID du buyer registre (string) OU tableau/DTO buyer inline.
     * @param array<string, mixed>|LineVatContext|null $line
     *   Contexte de la ligne. Defaut : STANDARD si null.
     *
     * @return VatResolution Resolution avec taux, categorie, code EN16931 et justification.
     */
    public function vatContext(
        string|array|BuyerContext $buyerOrInput,
        array|LineVatContext|null $line = null,
    ): VatResolution {
        $payload = $this->buildVatContextPayload($buyerOrInput, $line);
        $response = $this->http->post('tenant/buyers/vat-context', $payload);
        return VatResolution::fromArray($response['resolution']);
    }

    /**
     * @param string|array<string, mixed>|BuyerContext $buyerOrInput
     * @param array<string, mixed>|LineVatContext|null $line
     * @return array<string, mixed>
     */
    private function buildVatContextPayload(
        string|array|BuyerContext $buyerOrInput,
        array|LineVatContext|null $line,
    ): array {
        $payload = [];

        // --- buyer ---
        if (is_string($buyerOrInput)) {
            $payload['buyer_id'] = $buyerOrInput;
        } elseif ($buyerOrInput instanceof BuyerContext) {
            $payload['buyer'] = $buyerOrInput->toArray();
        } else {
            $payload['buyer'] = $buyerOrInput;
        }

        // --- line ---
        if ($line === null) {
            $payload['line'] = ['category' => VatCategory::Standard->value];
        } elseif ($line instanceof LineVatContext) {
            $payload['line'] = $line->toArray();
            // Garantir la presence de category si omise dans le DTO
            if (!isset($payload['line']['category'])) {
                $payload['line']['category'] = VatCategory::Standard->value;
            }
        } else {
            $payload['line'] = $line;
            if (!isset($payload['line']['category'])) {
                $payload['line']['category'] = VatCategory::Standard->value;
            }
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizePayload(array $data): array
    {
        $payload = $data;
        foreach (['billing_address', 'shipping_address'] as $key) {
            if (isset($payload[$key]) && $payload[$key] instanceof Address) {
                $payload[$key] = $payload[$key]->toArray();
            }
        }
        return $payload;
    }
}
