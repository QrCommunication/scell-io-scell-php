<?php

declare(strict_types=1);

namespace Scell\Sdk\Exceptions;

use Exception;
use Psr\Http\Message\ResponseInterface;

/**
 * Exception de base pour toutes les erreurs du SDK Scell.
 */
class ScellException extends Exception
{
    /**
     * Code d'erreur Scell (ex: 'VALIDATION_ERROR', 'INSUFFICIENT_BALANCE').
     */
    protected ?string $scellCode = null;

    /**
     * Corps de la reponse HTTP si disponible.
     */
    protected ?array $responseBody = null;

    /**
     * Code HTTP de la reponse.
     */
    protected ?int $httpStatusCode = null;

    /**
     * Cree une instance de ScellException.
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Exception $previous = null,
        ?string $scellCode = null,
        ?array $responseBody = null,
        ?int $httpStatusCode = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->scellCode = $scellCode;
        $this->responseBody = $responseBody;
        $this->httpStatusCode = $httpStatusCode;
    }

    /**
     * Cree une exception a partir d'une reponse HTTP.
     */
    public static function fromResponse(ResponseInterface $response): self
    {
        $statusCode = $response->getStatusCode();
        $body = json_decode($response->getBody()->getContents(), true) ?? [];
        $response->getBody()->rewind();

        $message = $body['message'] ?? $body['error'] ?? 'Une erreur est survenue';
        $scellCode = $body['code'] ?? null;

        // Determiner le type d'exception selon le code HTTP
        return match (true) {
            $statusCode === 401 => new AuthenticationException($message, $statusCode, null, $scellCode, $body, $statusCode),
            $statusCode === 422 => ValidationException::fromResponse($response),
            $statusCode === 429 => RateLimitException::fromResponse($response),
            default => new self($message, $statusCode, null, $scellCode, $body, $statusCode),
        };
    }

    /**
     * Retourne le code d'erreur Scell.
     */
    public function getScellCode(): ?string
    {
        return $this->scellCode;
    }

    /**
     * Retourne le corps de la reponse.
     */
    public function getResponseBody(): ?array
    {
        return $this->responseBody;
    }

    /**
     * Retourne le code HTTP.
     */
    public function getHttpStatusCode(): ?int
    {
        return $this->httpStatusCode;
    }

    /**
     * Verifie si l'erreur est une erreur de validation.
     */
    public function isValidationError(): bool
    {
        return $this->httpStatusCode === 422;
    }

    /**
     * Verifie si l'erreur est une erreur d'authentification.
     */
    public function isAuthenticationError(): bool
    {
        return $this->httpStatusCode === 401;
    }

    /**
     * Verifie si l'erreur est une erreur de rate limit.
     */
    public function isRateLimitError(): bool
    {
        return $this->httpStatusCode === 429;
    }
}
