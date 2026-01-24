<?php

declare(strict_types=1);

namespace Scell\Sdk\Exceptions;

/**
 * Exception levee lors d'une erreur d'authentification.
 *
 * Cette exception est levee lorsque:
 * - Le token Bearer est invalide ou expire
 * - L'API Key est invalide ou revoquee
 * - Les credentials sont manquants
 */
class AuthenticationException extends ScellException
{
    /**
     * Cree une exception pour un token invalide.
     */
    public static function invalidToken(): self
    {
        return new self(
            'Token d\'authentification invalide ou expire',
            401,
            null,
            'INVALID_TOKEN'
        );
    }

    /**
     * Cree une exception pour une API Key invalide.
     */
    public static function invalidApiKey(): self
    {
        return new self(
            'Cle API invalide ou revoquee',
            401,
            null,
            'INVALID_API_KEY'
        );
    }

    /**
     * Cree une exception pour des credentials manquants.
     */
    public static function missingCredentials(): self
    {
        return new self(
            'Aucune methode d\'authentification configuree. Utilisez un Bearer token ou une API Key.',
            401,
            null,
            'MISSING_CREDENTIALS'
        );
    }
}
