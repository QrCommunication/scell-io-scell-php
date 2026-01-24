<?php

declare(strict_types=1);

namespace Scell\Sdk\Enums;

/**
 * Statut de la facture dans le workflow.
 */
enum InvoiceStatus: string
{
    /** Brouillon - facture creee mais non traitee */
    case Draft = 'draft';

    /** En cours de traitement */
    case Processing = 'processing';

    /** Conversion en cours */
    case Converting = 'converting';

    /** Convertie - format cible genere */
    case Converted = 'converted';

    /** Validee - prete a etre transmise */
    case Validated = 'validated';

    /** Transmise au destinataire ou plateforme */
    case Transmitted = 'transmitted';

    /** Acceptee par le destinataire */
    case Accepted = 'accepted';

    /** Refusee par le destinataire */
    case Rejected = 'rejected';

    /** Erreur de traitement */
    case Error = 'error';

    /**
     * Retourne le libelle en francais.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Processing => 'En traitement',
            self::Converting => 'Conversion en cours',
            self::Converted => 'Convertie',
            self::Validated => 'Validee',
            self::Transmitted => 'Transmise',
            self::Accepted => 'Acceptee',
            self::Rejected => 'Refusee',
            self::Error => 'Erreur',
        };
    }

    /**
     * Verifie si le statut est final.
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::Accepted, self::Rejected, self::Error]);
    }

    /**
     * Verifie si le statut permet des actions.
     */
    public function isActionable(): bool
    {
        return in_array($this, [self::Validated, self::Converted]);
    }
}
