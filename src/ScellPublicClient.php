<?php

declare(strict_types=1);

namespace Scell\Sdk;

use Scell\Sdk\Http\HttpClient;
use Scell\Sdk\Resources\CreditPacksResource;
use Scell\Sdk\Resources\OnboardingResource;

/**
 * Client public Scell.io (Publishable Key — widget partenaire).
 *
 * Authentification via header `X-Publishable-Key` avec une cle `pk_live_*` ou
 * `pk_test_*`. Safe a utiliser cote serveur partenaire (proxy widget) ou dans
 * tout contexte ou la cle peut etre exposee — les publishable keys n'ont acces
 * qu'aux endpoints widget publics (`/widget/onboarding/*`).
 *
 * Symetrique du `ScellPublicClient` du SDK JS (`@scell/sdk`).
 *
 * @example
 * ```php
 * use Scell\Sdk\ScellPublicClient;
 *
 * $client = new ScellPublicClient('pk_live_...');
 *
 * // Recherche Sirene
 * $lookup = $client->onboarding()->lookupSirene('12345678901234');
 * if ($lookup->data !== null) {
 *     echo $lookup->data->name;
 * }
 *
 * // Creation SubTenant depuis le widget
 * $result = $client->onboarding()->createSubTenant([
 *     'company'  => $lookup->data,
 *     'identity' => [
 *         'first_name' => 'Marie',
 *         'last_name'  => 'Dupont',
 *         'email'      => 'marie@acme.fr',
 *     ],
 *     'locale'   => 'fr',
 * ]);
 * ```
 */
class ScellPublicClient
{
    private readonly HttpClient $http;
    private readonly Config $config;
    private ?OnboardingResource $onboarding = null;
    private ?CreditPacksResource $creditPacks = null;

    /**
     * @param string $publishableKey Cle publishable (pk_live_* ou pk_test_*)
     * @param Config|null $config Configuration optionnelle (sandbox / local / prod)
     */
    public function __construct(
        string $publishableKey,
        ?Config $config = null,
    ) {
        $this->config = $config ?? new Config();

        $this->http = new HttpClient(
            baseUrl: $this->config->baseUrl,
            timeout: $this->config->timeout,
            connectTimeout: $this->config->connectTimeout,
            retryAttempts: $this->config->retryAttempts,
            retryDelay: $this->config->retryDelay,
            verifySsl: $this->config->verifySsl,
        );

        $this->http->withPublishableKey($publishableKey);
    }

    /**
     * Cree un client en mode sandbox (pk_test_*).
     */
    public static function sandbox(string $publishableKey): self
    {
        return new self($publishableKey, Config::sandbox());
    }

    /**
     * Cree un client pour le developpement local.
     */
    public static function local(
        string $publishableKey,
        string $baseUrl = 'http://localhost:8000/api/v1',
    ): self {
        return new self($publishableKey, Config::local($baseUrl));
    }

    /**
     * Resource pour l'onboarding via SuperPDP / widget partenaire.
     */
    public function onboarding(): OnboardingResource
    {
        return $this->onboarding ??= new OnboardingResource($this->http);
    }

    /**
     * Resource pour les packs de credits Scell.io (publiques).
     *
     * Endpoint backend (`GET /api/v1/packs/public`) ne necessite aucune auth,
     * la publishable key suffit largement et garde la coherence du flow widget.
     *
     * @since 2.24.0
     */
    public function creditPacks(): CreditPacksResource
    {
        return $this->creditPacks ??= new CreditPacksResource($this->http);
    }

    /**
     * Retourne la configuration.
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * Retourne le client HTTP sous-jacent.
     */
    public function getHttpClient(): HttpClient
    {
        return $this->http;
    }
}
