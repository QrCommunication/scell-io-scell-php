<?php

declare(strict_types=1);

namespace Scell\Sdk\Exceptions;

/**
 * Exception levee quand on tente de convertir une ligne d'echeancier
 * en facture alors qu'elle a deja ete facturee.
 *
 * Code HTTP : 422 — Code Scell : SCHEDULE_LINE_ALREADY_INVOICED
 */
final class ScheduleLineAlreadyInvoicedException extends ScellException
{
    public function __construct(
        string $message = 'Cette ligne d\'echéancier a deja ete convertie en facture.',
        ?array $responseBody = null,
    ) {
        parent::__construct(
            message: $message,
            code: 422,
            scellCode: 'SCHEDULE_LINE_ALREADY_INVOICED',
            responseBody: $responseBody,
            httpStatusCode: 422,
        );
    }
}
