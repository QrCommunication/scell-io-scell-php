<?php

declare(strict_types=1);

namespace Scell\Sdk\Exceptions;

/**
 * Exception levee quand le serveur detecte un taux de TVA incoherent avec le
 * contexte vendeur/acheteur (autoliquidation intra-UE non appliquee, exoneration
 * manquante, franchise en base...) et qu'aucune raison d'override n'a ete fournie.
 *
 * La facture N'EST PAS persistee. La reponse porte la liste des corrections
 * proposees (taux + categorie + mention suggeres par ligne).
 *
 * Code HTTP : 409 — Code Scell : VAT_CORRECTION_REQUIRED
 *
 * Deux issues possibles cote integrateur :
 *  1. Re-soumettre la facture avec les `suggested_rate` / `suggested_category`
 *     proposes (voir {@see getCorrections()}).
 *  2. Conserver SON taux en l'assumant : ajouter `vat_override_reason` sur la
 *     ligne concernee (cf. {@see \Scell\Sdk\Builders\InvoiceLineBuilder::withOverrideReason()}).
 *
 * @example
 * ```php
 * use Scell\Sdk\Exceptions\VatCorrectionRequiredException;
 *
 * try {
 *     $client->invoices()->create($payload);
 * } catch (VatCorrectionRequiredException $e) {
 *     foreach ($e->getCorrections() as $c) {
 *         printf(
 *             "Ligne %d : %s%% -> %s%% (%s) — %s\n",
 *             $c['line_index'],
 *             $c['provided_rate'],
 *             $c['suggested_rate'],
 *             $c['suggested_category'],
 *             $c['mention'] ?? ''
 *         );
 *     }
 * }
 * ```
 */
final class VatCorrectionRequiredException extends ScellException
{
    public function __construct(
        string $message = "Le taux de TVA d'une ou plusieurs lignes est incoherent avec le contexte vendeur/acheteur.",
        ?array $responseBody = null,
    ) {
        parent::__construct(
            message: $message,
            code: 409,
            scellCode: 'VAT_CORRECTION_REQUIRED',
            responseBody: $responseBody,
            httpStatusCode: 409,
        );
    }

    /**
     * Corrections proposees, une par ligne incoherente.
     *
     * Chaque entree :
     *  - `line_index`        (int)    index 0-based de la ligne dans le payload
     *  - `description`       (?string)
     *  - `provided_rate`     (?float) taux que VOUS avez fourni
     *  - `suggested_rate`    (float)  taux resolu par le serveur
     *  - `suggested_category`(string) categorie EN16931 applicative (REVERSE_CHARGE, INTRACOM_GOODS...)
     *  - `en16931_code`      (string) code de marche (AE, K, G, O, E, S, Z)
     *  - `mention`           (?string) mention legale a inscrire sur la facture
     *  - `rule`              (?string) identifiant de regle (audit)
     *  - `warning`           (?string) message d'alerte
     *
     * @return list<array<string, mixed>>
     */
    public function getCorrections(): array
    {
        return $this->responseBody['corrections'] ?? [];
    }

    /**
     * Indication d'action retournee par l'API.
     */
    public function getHint(): ?string
    {
        return $this->responseBody['hint'] ?? null;
    }
}
