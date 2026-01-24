<?php

declare(strict_types=1);

namespace Scell\Sdk\Exceptions;

use Psr\Http\Message\ResponseInterface;

/**
 * Exception levee lors d'une erreur de validation.
 *
 * Cette exception contient les details des erreurs de validation
 * retournees par l'API, avec les champs concernes.
 */
class ValidationException extends ScellException
{
    /**
     * Erreurs de validation par champ.
     *
     * @var array<string, string[]>
     */
    protected array $errors = [];

    /**
     * Cree une instance de ValidationException.
     *
     * @param array<string, string[]> $errors
     */
    public function __construct(
        string $message = 'Erreur de validation',
        array $errors = [],
        ?array $responseBody = null
    ) {
        parent::__construct($message, 422, null, 'VALIDATION_ERROR', $responseBody, 422);
        $this->errors = $errors;
    }

    /**
     * Cree une exception a partir d'une reponse HTTP.
     */
    public static function fromResponse(ResponseInterface $response): self
    {
        $body = json_decode($response->getBody()->getContents(), true) ?? [];
        $response->getBody()->rewind();

        $message = $body['message'] ?? 'Erreur de validation';
        $errors = $body['errors'] ?? [];

        return new self($message, $errors, $body);
    }

    /**
     * Retourne toutes les erreurs de validation.
     *
     * @return array<string, string[]>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Retourne les erreurs pour un champ specifique.
     *
     * @return string[]
     */
    public function getFieldErrors(string $field): array
    {
        return $this->errors[$field] ?? [];
    }

    /**
     * Retourne la premiere erreur pour un champ.
     */
    public function getFirstFieldError(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }

    /**
     * Verifie si un champ a des erreurs.
     */
    public function hasFieldError(string $field): bool
    {
        return isset($this->errors[$field]) && count($this->errors[$field]) > 0;
    }

    /**
     * Retourne tous les champs en erreur.
     *
     * @return string[]
     */
    public function getFailedFields(): array
    {
        return array_keys($this->errors);
    }

    /**
     * Retourne toutes les erreurs sous forme de liste plate.
     *
     * @return string[]
     */
    public function getAllMessages(): array
    {
        $messages = [];
        foreach ($this->errors as $fieldErrors) {
            $messages = array_merge($messages, $fieldErrors);
        }
        return $messages;
    }
}
