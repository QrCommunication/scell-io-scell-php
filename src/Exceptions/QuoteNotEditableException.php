<?php

declare(strict_types=1);

namespace Scell\Sdk\Exceptions;

/**
 * Exception levee quand une tentative de modification est faite
 * sur un echéancier verrouille (devis signe/accepte).
 *
 * Code HTTP : 409 — Code Scell : QUOTE_NOT_EDITABLE
 */
final class QuoteNotEditableException extends ScellException
{
    public function __construct(
        string $message = "Le devis n'est pas modifiable (statut incompatible ou echéancier verrouille).",
        ?array $responseBody = null,
    ) {
        parent::__construct(
            message: $message,
            code: 409,
            scellCode: 'QUOTE_NOT_EDITABLE',
            responseBody: $responseBody,
            httpStatusCode: 409,
        );
    }
}
