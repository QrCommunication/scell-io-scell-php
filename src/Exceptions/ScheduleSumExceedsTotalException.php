<?php

declare(strict_types=1);

namespace Scell\Sdk\Exceptions;

/**
 * Exception levee quand la somme des lignes d'echeancier depasse
 * 100% du total TTC du devis.
 *
 * Code HTTP : 422 — Code Scell : SCHEDULE_SUM_EXCEEDS_TOTAL
 */
final class ScheduleSumExceedsTotalException extends ScellException
{
    public function __construct(
        string $message = 'La somme des lignes d\'echéancier dépasse 100% du total TTC du devis.',
        ?array $responseBody = null,
    ) {
        parent::__construct(
            message: $message,
            code: 422,
            scellCode: 'SCHEDULE_SUM_EXCEEDS_TOTAL',
            responseBody: $responseBody,
            httpStatusCode: 422,
        );
    }
}
