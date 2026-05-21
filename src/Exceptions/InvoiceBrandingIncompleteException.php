<?php

declare(strict_types=1);

namespace Scell\Sdk\Exceptions;

/**
 * Exception levee quand l'envoi personnalise est demande mais que
 * le branding tenant/sub-tenant est incomplet (logo, couleur ou
 * footer manquant).
 *
 * L'API fallback sur le branding Scell.io si ce champ n'est pas
 * explicitement force. Cette exception n'est levee que quand
 * l'appelant demande un branding tenant obligatoire et que celui-ci
 * est incomplet.
 *
 * Code HTTP : 422 — Code Scell : INVOICE_BRANDING_INCOMPLETE
 */
final class InvoiceBrandingIncompleteException extends ScellException
{
    public function __construct(
        string $message = 'Le branding tenant est incomplet (logo, couleur primaire et footer requis). Completez-le ou laissez l\'API utiliser le branding Scell.io par defaut.',
        ?array $responseBody = null,
    ) {
        parent::__construct(
            message: $message,
            code: 422,
            scellCode: 'INVOICE_BRANDING_INCOMPLETE',
            responseBody: $responseBody,
            httpStatusCode: 422,
        );
    }
}
