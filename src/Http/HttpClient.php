<?php

declare(strict_types=1);

namespace Scell\Sdk\Http;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use Scell\Sdk\Exceptions\AuthenticationException;
use Scell\Sdk\Exceptions\ScellException;

/**
 * Client HTTP pour l'API Scell.io.
 *
 * Wrapper autour de Guzzle avec:
 * - Retry automatique avec backoff exponentiel
 * - Gestion des erreurs unifiee
 * - Headers par defaut
 */
class HttpClient
{
    private Client $client;
    private string $baseUrl;
    private ?string $bearerToken = null;
    private ?string $apiKey = null;
    private ?string $tenantKey = null;

    /**
     * Cree une instance du client HTTP.
     */
    public function __construct(
        string $baseUrl,
        int $timeout = 30,
        int $connectTimeout = 10,
        int $retryAttempts = 3,
        int $retryDelay = 100,
        bool $verifySsl = true,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');

        $stack = HandlerStack::create();
        $stack->push(RetryMiddleware::create($retryAttempts, $retryDelay));

        $this->client = new Client([
            'handler' => $stack,
            'timeout' => $timeout,
            'connect_timeout' => $connectTimeout,
            'verify' => $verifySsl,
            'http_errors' => false, // On gere les erreurs manuellement
        ]);
    }

    /**
     * Configure l'authentification par Bearer token.
     */
    public function withBearerToken(string $token): self
    {
        $this->bearerToken = $token;
        $this->apiKey = null;
        return $this;
    }

    /**
     * Configure l'authentification par API Key.
     */
    public function withApiKey(string $apiKey): self
    {
        $this->apiKey = $apiKey;
        $this->bearerToken = null;
        $this->tenantKey = null;
        return $this;
    }

    /**
     * Configure l'authentification par Tenant Key.
     */
    public function withTenantKey(string $tenantKey): self
    {
        $this->tenantKey = $tenantKey;
        $this->bearerToken = null;
        $this->apiKey = null;
        return $this;
    }

    /**
     * Effectue une requete GET.
     *
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     * @throws ScellException
     */
    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, [
            RequestOptions::QUERY => $query,
        ]);
    }

    /**
     * Effectue une requete POST.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     * @throws ScellException
     */
    public function post(string $path, array $data = []): array
    {
        return $this->request('POST', $path, [
            RequestOptions::JSON => $data,
        ]);
    }

    /**
     * Effectue une requete PUT.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     * @throws ScellException
     */
    public function put(string $path, array $data = []): array
    {
        return $this->request('PUT', $path, [
            RequestOptions::JSON => $data,
        ]);
    }

    /**
     * Effectue une requete DELETE.
     *
     * @return array<string, mixed>
     * @throws ScellException
     */
    public function delete(string $path): array
    {
        return $this->request('DELETE', $path);
    }

    /**
     * Effectue une requete GET et retourne le contenu brut (binaire).
     *
     * Utile pour telecharger des fichiers (PDF, XML, etc.)
     *
     * @param array<string, mixed> $query
     * @return string Contenu binaire du fichier
     * @throws ScellException
     */
    public function getRaw(string $path, array $query = []): string
    {
        return $this->requestRaw('GET', $path, [
            RequestOptions::QUERY => $query,
        ]);
    }

    /**
     * Effectue une requete HTTP.
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     * @throws ScellException
     */
    private function request(string $method, string $path, array $options = []): array
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/');

        $options[RequestOptions::HEADERS] = array_merge(
            $options[RequestOptions::HEADERS] ?? [],
            $this->getDefaultHeaders()
        );

        try {
            $response = $this->client->request($method, $url, $options);
            return $this->handleResponse($response);
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            throw new ScellException(
                'Erreur de connexion: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Effectue une requete HTTP et retourne le contenu brut.
     *
     * @param array<string, mixed> $options
     * @return string
     * @throws ScellException
     */
    private function requestRaw(string $method, string $path, array $options = []): string
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/');

        $headers = $this->getDefaultHeaders();
        // Pour les requetes raw, on accepte n'importe quel type de contenu
        $headers['Accept'] = '*/*';
        unset($headers['Content-Type']);

        $options[RequestOptions::HEADERS] = array_merge(
            $options[RequestOptions::HEADERS] ?? [],
            $headers
        );

        try {
            $response = $this->client->request($method, $url, $options);
            return $this->handleRawResponse($response);
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            throw new ScellException(
                'Erreur de connexion: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Retourne les headers par defaut.
     *
     * @return array<string, string>
     */
    private function getDefaultHeaders(): array
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'User-Agent' => 'Scell-PHP-SDK/1.2.0',
        ];

        if ($this->bearerToken) {
            $headers['Authorization'] = 'Bearer ' . $this->bearerToken;
        } elseif ($this->apiKey) {
            $headers['X-API-Key'] = $this->apiKey;
        } elseif ($this->tenantKey) {
            $headers['X-Tenant-Key'] = $this->tenantKey;
        }

        return $headers;
    }

    /**
     * Traite la reponse HTTP.
     *
     * @return array<string, mixed>
     * @throws ScellException
     */
    private function handleResponse(ResponseInterface $response): array
    {
        $statusCode = $response->getStatusCode();
        $body = json_decode($response->getBody()->getContents(), true);
        $response->getBody()->rewind();

        // Succes
        if ($statusCode >= 200 && $statusCode < 300) {
            return $body ?? [];
        }

        // Erreur
        throw ScellException::fromResponse($response);
    }

    /**
     * Traite la reponse HTTP et retourne le contenu brut.
     *
     * @return string
     * @throws ScellException
     */
    private function handleRawResponse(ResponseInterface $response): string
    {
        $statusCode = $response->getStatusCode();

        // Succes
        if ($statusCode >= 200 && $statusCode < 300) {
            return $response->getBody()->getContents();
        }

        // Erreur
        throw ScellException::fromResponse($response);
    }

    /**
     * Verifie si le client a des credentials configures.
     */
    public function hasCredentials(): bool
    {
        return $this->bearerToken !== null || $this->apiKey !== null || $this->tenantKey !== null;
    }

    /**
     * Retourne le client Guzzle sous-jacent.
     */
    public function getGuzzleClient(): Client
    {
        return $this->client;
    }
}
