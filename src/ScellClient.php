<?php

declare(strict_types=1);

namespace Scell\Sdk;

use Scell\Sdk\Http\HttpClient;
use Scell\Sdk\Resources\BalanceResource;
use Scell\Sdk\Resources\CompanyResource;
use Scell\Sdk\Resources\InvoiceResource;
use Scell\Sdk\Resources\SignatureResource;
use Scell\Sdk\Resources\WebhookResource;

/**
 * Client principal du SDK Scell.io (authentification Bearer token).
 *
 * Utilise ce client pour les operations via le dashboard (utilisateur connecte).
 *
 * @example
 * ```php
 * // Initialisation avec Bearer token
 * $client = new ScellClient($bearerToken);
 *
 * // Ou avec configuration personnalisee
 * $client = new ScellClient($bearerToken, new Config(
 *     baseUrl: 'https://api.scell.io/api/v1',
 *     timeout: 60,
 * ));
 *
 * // Lister les factures
 * $invoices = $client->invoices()->list();
 *
 * // Creer une signature
 * $signature = $client->signatures()->builder()
 *     ->title('Contrat de prestation')
 *     ->documentFromFile('/path/to/contract.pdf')
 *     ->addEmailSigner('Jean', 'Dupont', 'jean@example.com')
 *     ->create();
 * ```
 */
class ScellClient
{
    private readonly HttpClient $http;
    private ?InvoiceResource $invoices = null;
    private ?SignatureResource $signatures = null;
    private ?CompanyResource $companies = null;
    private ?BalanceResource $balance = null;
    private ?WebhookResource $webhooks = null;

    /**
     * Cree une instance du client avec Bearer token.
     *
     * @param string $bearerToken Token d'authentification (obtenu via /auth/login)
     * @param Config|null $config Configuration optionnelle
     */
    public function __construct(
        string $bearerToken,
        ?Config $config = null
    ) {
        $config ??= new Config();

        $this->http = new HttpClient(
            baseUrl: $config->baseUrl,
            timeout: $config->timeout,
            connectTimeout: $config->connectTimeout,
            retryAttempts: $config->retryAttempts,
            retryDelay: $config->retryDelay,
            verifySsl: $config->verifySsl,
        );

        $this->http->withBearerToken($bearerToken);
    }

    /**
     * Resource pour les factures.
     */
    public function invoices(): InvoiceResource
    {
        return $this->invoices ??= new InvoiceResource($this->http);
    }

    /**
     * Resource pour les signatures.
     */
    public function signatures(): SignatureResource
    {
        return $this->signatures ??= new SignatureResource($this->http);
    }

    /**
     * Resource pour les entreprises.
     */
    public function companies(): CompanyResource
    {
        return $this->companies ??= new CompanyResource($this->http);
    }

    /**
     * Resource pour le solde.
     */
    public function balance(): BalanceResource
    {
        return $this->balance ??= new BalanceResource($this->http);
    }

    /**
     * Resource pour les webhooks.
     */
    public function webhooks(): WebhookResource
    {
        return $this->webhooks ??= new WebhookResource($this->http);
    }

    /**
     * Retourne le client HTTP sous-jacent.
     *
     * Utile pour les requetes personnalisees.
     */
    public function getHttpClient(): HttpClient
    {
        return $this->http;
    }
}
