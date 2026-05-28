<?php

declare(strict_types=1);

namespace Scell\Sdk\Resources;

use Scell\Sdk\DTOs\CreditPack;
use Scell\Sdk\Http\HttpClient;

/**
 * Resource pour les packs de credits Scell.io (one-shot prepaid avec bonus).
 *
 * Expose deux endpoints publics (ne necessitent pas d'authentification tenant
 * pour la lecture, le checkout en revanche necessite une cle `sk_*`) :
 *
 *  - `GET /api/v1/packs/public` — liste les packs actifs
 *  - `GET /api/v1/packs/public` filtre client-side par slug pour `get()`
 *
 * Pour acheter un pack, voir `BillingResource::checkoutPack($slug)` ou
 * `ScellApiClient::withApiKey($sk)->billing()->checkoutPack($slug)`.
 *
 * **Cache** : l'endpoint backend renvoie `Cache-Control: public, max-age=3600`.
 * Le SDK ne mette pas en cache cote client — c'est au caller de cacher si besoin.
 *
 * @example
 * ```php
 * // Public client (publishable key suffit, ou sk_*)
 * $client = ScellApiClient::withPublishableKey('pk_live_xxx');
 * $packs = $client->creditPacks()->list();
 * foreach ($packs as $pack) {
 *     echo "{$pack->name} — {$pack->amountEuros} EUR -> {$pack->creditsEuros} EUR de credits";
 *     if ($pack->hasBonus()) {
 *         echo " (+{$pack->bonusPercent}%)";
 *     }
 * }
 *
 * // Recuperer un pack precis
 * $pro = $client->creditPacks()->get('pro');
 * ```
 *
 * @since 2.24.0
 */
class CreditPacksResource
{
    public function __construct(
        private readonly HttpClient $http
    ) {}

    /**
     * Liste tous les packs de credits actifs.
     *
     * Endpoint : `GET /api/v1/packs/public`. Pas d'auth requise (publishable
     * key ou sk_* acceptes — voir route backend).
     *
     * @return CreditPack[] Liste ordonnee par `position` croissante (defini cote admin)
     */
    public function list(): array
    {
        $response = $this->http->get('packs/public');
        $items = $response['data'] ?? [];

        return array_map(
            fn(array $data): CreditPack => CreditPack::fromArray($data),
            $items,
        );
    }

    /**
     * Recupere un pack par son slug (`starter`, `pro`, `business`, ...).
     *
     * Note : le backend n'expose pas (encore) d'endpoint `GET /packs/public/{slug}`,
     * cette methode filtre la liste client-side. Si le slug est introuvable,
     * leve une `ScellException` 404.
     *
     * @param string $slug Slug stable du pack
     *
     * @return CreditPack
     *
     * @throws \Scell\Sdk\Exceptions\ScellException Si le slug n'existe pas / n'est pas actif
     */
    public function get(string $slug): CreditPack
    {
        foreach ($this->list() as $pack) {
            if ($pack->slug === $slug) {
                return $pack;
            }
        }

        throw new \Scell\Sdk\Exceptions\ScellException(
            "Credit pack with slug '{$slug}' not found or not active.",
            404,
        );
    }
}
