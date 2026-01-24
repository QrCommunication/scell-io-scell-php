<?php

declare(strict_types=1);

namespace Scell\Sdk\Http;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Middleware de retry avec backoff exponentiel et jitter.
 */
class RetryMiddleware
{
    /**
     * Codes HTTP pour lesquels on doit retenter.
     */
    private const RETRYABLE_STATUS_CODES = [
        408, // Request Timeout
        429, // Too Many Requests
        500, // Internal Server Error
        502, // Bad Gateway
        503, // Service Unavailable
        504, // Gateway Timeout
    ];

    /**
     * Cree le middleware de retry.
     *
     * @param int $maxRetries Nombre maximum de tentatives
     * @param int $baseDelay Delai de base en millisecondes
     */
    public static function create(int $maxRetries = 3, int $baseDelay = 100): callable
    {
        return Middleware::retry(
            self::decider($maxRetries),
            self::delay($baseDelay)
        );
    }

    /**
     * Decide si une requete doit etre retentee.
     */
    private static function decider(int $maxRetries): callable
    {
        return function (
            int $retries,
            RequestInterface $request,
            ?ResponseInterface $response = null,
            ?\Throwable $exception = null
        ) use ($maxRetries): bool {
            // Ne pas retenter au-dela du maximum
            if ($retries >= $maxRetries) {
                return false;
            }

            // Retenter sur les erreurs de connexion
            if ($exception instanceof ConnectException) {
                return true;
            }

            // Ne pas retenter les methodes non-idempotentes (sauf GET/HEAD)
            $method = $request->getMethod();
            if (!in_array($method, ['GET', 'HEAD', 'PUT', 'DELETE', 'OPTIONS'])) {
                // Pour POST, verifier si c'est une erreur serveur (5xx)
                if ($response && $response->getStatusCode() >= 500) {
                    return true;
                }
                // Retenter aussi sur 429 (rate limit) pour POST
                if ($response && $response->getStatusCode() === 429) {
                    return true;
                }
                return false;
            }

            // Retenter sur les codes HTTP retryables
            if ($response && in_array($response->getStatusCode(), self::RETRYABLE_STATUS_CODES)) {
                return true;
            }

            return false;
        };
    }

    /**
     * Calcule le delai avant la prochaine tentative.
     *
     * Utilise un backoff exponentiel avec jitter:
     * delay = baseDelay * 2^retries * (1 + random jitter)
     */
    private static function delay(int $baseDelay): callable
    {
        return function (int $retries, ?ResponseInterface $response) use ($baseDelay): int {
            // Verifier le header Retry-After
            if ($response && $response->hasHeader('Retry-After')) {
                $retryAfter = $response->getHeaderLine('Retry-After');

                // Si c'est un nombre, c'est le delai en secondes
                if (is_numeric($retryAfter)) {
                    return (int) $retryAfter * 1000;
                }

                // Sinon c'est une date HTTP
                $date = \DateTimeImmutable::createFromFormat(\DateTimeInterface::RFC7231, $retryAfter);
                if ($date) {
                    $diff = $date->getTimestamp() - time();
                    return max(0, $diff * 1000);
                }
            }

            // Backoff exponentiel: baseDelay * 2^retries
            $exponentialDelay = $baseDelay * (2 ** $retries);

            // Ajouter un jitter aleatoire (0-25% du delai)
            $jitter = (int) ($exponentialDelay * (mt_rand(0, 25) / 100));

            return $exponentialDelay + $jitter;
        };
    }
}
