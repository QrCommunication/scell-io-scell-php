<?php

declare(strict_types=1);

namespace Scell\Sdk\Enums;

/**
 * Statut de la facture dans le workflow.
 *
 * Aligne sur le check constraint PostgreSQL `invoices_status_check` du backend
 * Scell.io. Les statuts `refunded` et `partially_refunded` sont poses
 * automatiquement par `CreditNoteObserver` cote backend quand un avoir
 * (credit_note) atteint un statut `validated`/`sent`/`transmitted`/`accepted`
 * et que la somme des avoirs valides depasse (full) ou est inferieure (partial)
 * au total TTC de la facture.
 *
 * @see RefundStatus Enum dedie au champ `refund_status` retourne par l'API
 *      (none/partial/full) qui est plus simple a consommer que de raisonner
 *      directement sur le statut.
 */
enum InvoiceStatus: string
{
    /** Brouillon - facture creee mais non traitee */
    case Draft = 'draft';

    /**
     * En cours de traitement (alias historique de Validating).
     *
     * @deprecated Le backend Scell.io expose `validating`. `Processing` reste
     *             disponible pour retrocompatibilite avec les anciennes
     *             integrations qui posaient encore cette valeur. Ne plus poser
     *             dans du nouveau code.
     */
    case Processing = 'processing';

    /** Validation en cours (file de jobs, controles Factur-X/UBL/CII) */
    case Validating = 'validating';

    /** Conversion en cours */
    case Converting = 'converting';

    /** Convertie - format cible genere */
    case Converted = 'converted';

    /** Validee - prete a etre transmise */
    case Validated = 'validated';

    /** Transmission en cours vers la plateforme (SuperPDP, Peppol, ...) */
    case Transmitting = 'transmitting';

    /** Transmise au destinataire ou plateforme */
    case Transmitted = 'transmitted';

    /** Acceptee par le destinataire */
    case Accepted = 'accepted';

    /** Refusee par le destinataire */
    case Rejected = 'rejected';

    /** Contestee par le destinataire (litige ouvert) */
    case Disputed = 'disputed';

    /** Payee - facture entrante marquee comme payee */
    case Paid = 'paid';

    /** Recue cote acheteur (entrante) */
    case Received = 'received';

    /** Cloturee - cycle de vie termine */
    case Completed = 'completed';

    /** Erreur de traitement */
    case Error = 'error';

    /**
     * Totalement avoiree.
     *
     * Pose automatiquement par le backend via `CreditNoteObserver` quand la
     * somme des avoirs valides associes a la facture atteint (ou depasse a
     * 0.001 EUR pres) le total TTC. La facture reste consultable mais ne peut
     * plus etre encaissee. Une facture `paid` puis avoiree integralement bascule
     * en `refunded`.
     */
    case Refunded = 'refunded';

    /**
     * Partiellement avoiree.
     *
     * Pose automatiquement quand au moins un avoir valide existe mais que la
     * somme totale des avoirs reste strictement inferieure au TTC. Permet de
     * distinguer un remboursement partiel d'un remboursement integral.
     */
    case PartiallyRefunded = 'partially_refunded';

    /**
     * Retourne le libelle en francais.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Processing => 'En traitement',
            self::Validating => 'Validation en cours',
            self::Converting => 'Conversion en cours',
            self::Converted => 'Convertie',
            self::Validated => 'Validee',
            self::Transmitting => 'Transmission en cours',
            self::Transmitted => 'Transmise',
            self::Accepted => 'Acceptee',
            self::Rejected => 'Refusee',
            self::Disputed => 'Contestee',
            self::Paid => 'Payee',
            self::Received => 'Recue',
            self::Completed => 'Cloturee',
            self::Error => 'Erreur',
            self::Refunded => 'Avoiree',
            self::PartiallyRefunded => 'Partiellement avoiree',
        };
    }

    /**
     * Verifie si le statut est final (la facture n'evolue plus).
     */
    public function isFinal(): bool
    {
        return in_array($this, [
            self::Accepted,
            self::Rejected,
            self::Paid,
            self::Completed,
            self::Error,
            self::Refunded,
        ], true);
    }

    /**
     * Verifie si le statut permet des actions (validation manuelle, conversion).
     */
    public function isActionable(): bool
    {
        return in_array($this, [self::Validated, self::Converted], true);
    }

    /**
     * Indique si la facture a ete avoiree (totalement ou partiellement).
     *
     * Disponible depuis SDK 2.20.0.
     */
    public function isRefunded(): bool
    {
        return $this === self::Refunded || $this === self::PartiallyRefunded;
    }
}
