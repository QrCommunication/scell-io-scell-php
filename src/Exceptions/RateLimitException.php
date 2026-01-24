<?php

declare(strict_types=1);

namespace Scell\Sdk\Exceptions;

use Psr\Http\Message\ResponseInterface;

/**
 * Exception levee lorsque la limite de requetes est atteinte.
 *
 * Cette exception contient les informations sur la limite de taux
 * et le temps d'attente avant la prochaine tentative.
 */
class RateLimitException extends ScellException
{
    /**
     * Nombre de requetes maximum autorisees.
     */
    protected ?int $limit = null;

    /**
     * Nombre de requetes restantes.
     */
    protected ?int $remaining = null;

    /**
     * Timestamp de reset de la fenetre de rate limit.
     */
    protected ?int $resetAt = null;

    /**
     * Secondes avant le prochain essai possible.
     */
    protected ?int $retryAfter = null;

    /**
     * Cree une instance de RateLimitException.
     */
    public function __construct(
        string $message = 'Limite de requetes atteinte',
        ?int $limit = null,
        ?int $remaining = null,
        ?int $resetAt = null,
        ?int $retryAfter = null,
        ?array $responseBody = null
    ) {
        parent::__construct($message, 429, null, 'RATE_LIMIT_EXCEEDED', $responseBody, 429);
        $this->limit = $limit;
        $this->remaining = $remaining;
        $this->resetAt = $resetAt;
        $this->retryAfter = $retryAfter;
    }

    /**
     * Cree une exception a partir d'une reponse HTTP.
     */
    public static function fromResponse(ResponseInterface $response): self
    {
        $body = json_decode($response->getBody()->getContents(), true) ?? [];
        $response->getBody()->rewind();

        $message = $body['message'] ?? 'Limite de requetes atteinte. Reessayez plus tard.';

        // Extraire les headers de rate limit
        $limit = self::getHeaderInt($response, 'X-RateLimit-Limit');
        $remaining = self::getHeaderInt($response, 'X-RateLimit-Remaining');
        $resetAt = self::getHeaderInt($response, 'X-RateLimit-Reset');
        $retryAfter = self::getHeaderInt($response, 'Retry-After');

        return new self($message, $limit, $remaining, $resetAt, $retryAfter, $body);
    }

    /**
     * Extrait un header en entier.
     */
    private static function getHeaderInt(ResponseInterface $response, string $header): ?int
    {
        $value = $response->getHeaderLine($header);
        return $value !== '' ? (int) $value : null;
    }

    /**
     * Retourne la limite de requetes.
     */
    public function getLimit(): ?int
    {
        return $this->limit;
    }

    /**
     * Retourne le nombre de requetes restantes.
     */
    public function getRemaining(): ?int
    {
        return $this->remaining;
    }

    /**
     * Retourne le timestamp de reset.
     */
    public function getResetAt(): ?int
    {
        return $this->resetAt;
    }

    /**
     * Retourne le nombre de secondes avant retry.
     */
    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }

    /**
     * Retourne le timestamp de reset sous forme de DateTime.
     */
    public function getResetDateTime(): ?\DateTimeImmutable
    {
        if ($this->resetAt === null) {
            return null;
        }

        return (new \DateTimeImmutable())->setTimestamp($this->resetAt);
    }
}
