<?php

declare(strict_types=1);

namespace Scell\Sdk\Exceptions;

/**
 * Exception levee quand l'envoi d'une facture par email est demande
 * mais que l'acheteur n'a ni email ni billing_email renseigne.
 *
 * Code HTTP : 422 — Code Scell : BUYER_HAS_NO_EMAIL
 */
final class BuyerHasNoEmailException extends ScellException
{
    public function __construct(
        string $message = "L'acheteur n'a pas d'adresse email. Renseignez buyer.email ou buyer.billing_email avant d'envoyer la facture.",
        ?array $responseBody = null,
    ) {
        parent::__construct(
            message: $message,
            code: 422,
            scellCode: 'BUYER_HAS_NO_EMAIL',
            responseBody: $responseBody,
            httpStatusCode: 422,
        );
    }
}
