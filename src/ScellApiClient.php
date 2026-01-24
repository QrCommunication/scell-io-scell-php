<?php

declare(strict_types=1);

namespace Scell\Sdk;

use Scell\Sdk\Http\HttpClient;
use Scell\Sdk\Resources\InvoiceResource;
use Scell\Sdk\Resources\SignatureResource;

/**
 * Client API Scell.io (authentification par API Key).
 *
 * Utilise ce client pour les integrations serveur-a-serveur (backend).
 * Les operations passent par les routes /api/v1 avec X-API-Key header.
 *
 * @example
 * ```php
 * // Initialisation avec API Key
 * $api = ScellApiClient::withApiKey('sk_live_...');
 *
 * // Ou mode sandbox
 * $api = ScellApiClient::sandbox('sk_test_...');
 *
 * // Creer une facture
 * $invoice = $api->invoices()->builder()
 *     ->invoiceNumber('FACT-2024-001')
 *     ->outgoing()
 *     ->facturX()
 *     ->issueDate(new \DateTime())
 *     ->seller('12345678901234', 'Ma Societe', new Address(...))
 *     ->buyer('98765432109876', 'Client SA', new Address(...))
 *     ->addLine('Prestation', 1, 1000.00, 20.0)
 *     ->create();
 *
 * // Creer une signature
 * $signature = $api->signatures()->builder()
 *     ->title('Contrat')
 *     ->documentFromFile('/path/to/doc.pdf')
 *     ->addEmailSigner('Jean', 'Dupont', 'jean@example.com')
 *     ->create();
 * ```
 */
class ScellApiClient
{
    private readonly HttpClient $http;
    private readonly Config $config;
    private ?InvoiceResource $invoices = null;
    private ?SignatureResource $signatures = null;

    /**
     * Cree une instance du client API.
     *
     * @param string $apiKey Cle API (commence par sk_live_ ou sk_test_)
     * @param Config|null $config Configuration optionnelle
     */
    private function __construct(
        string $apiKey,
        ?Config $config = null
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

        $this->http->withApiKey($apiKey);
    }

    /**
     * Cree un client avec une API Key.
     *
     * @param string $apiKey Cle API (sk_live_... ou sk_test_...)
     * @param Config|null $config Configuration optionnelle
     */
    public static function withApiKey(string $apiKey, ?Config $config = null): self
    {
        return new self($apiKey, $config);
    }

    /**
     * Cree un client en mode sandbox.
     *
     * @param string $apiKey Cle API sandbox (sk_test_...)
     */
    public static function sandbox(string $apiKey): self
    {
        return new self($apiKey, Config::sandbox());
    }

    /**
     * Cree un client pour le developpement local.
     *
     * @param string $apiKey Cle API
     * @param string $baseUrl URL de l'API locale
     */
    public static function local(string $apiKey, string $baseUrl = 'http://localhost:8000/api/v1'): self
    {
        return new self($apiKey, Config::local($baseUrl));
    }

    /**
     * Resource pour les factures.
     *
     * Endpoints disponibles:
     * - POST /invoices - Creer une facture
     * - GET /invoices/{id}/download/{type} - Telecharger
     * - GET /invoices/{id}/audit-trail - Piste d'audit
     * - POST /invoices/convert - Convertir format
     */
    public function invoices(): InvoiceResource
    {
        return $this->invoices ??= new InvoiceResource($this->http);
    }

    /**
     * Resource pour les signatures.
     *
     * Endpoints disponibles:
     * - POST /signatures - Creer une demande
     * - GET /signatures/{id}/download/{type} - Telecharger
     * - POST /signatures/{id}/remind - Envoyer rappel
     * - POST /signatures/{id}/cancel - Annuler
     */
    public function signatures(): SignatureResource
    {
        return $this->signatures ??= new SignatureResource($this->http);
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
