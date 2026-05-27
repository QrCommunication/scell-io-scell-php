<?php

declare(strict_types=1);

namespace Scell\Sdk\Enums;

/**
 * Statut du devis dans son cycle de vie.
 *
 * Aligne sur `App\Enums\Quote\QuoteStatus` du backend Scell.io.
 *
 * Cycle nominal :
 *   draft -> sent -> viewed -> accepted -> converted (facturation)
 *                            -> refused
 *                            -> expired
 *                            -> cancelled
 *
 * Disponible depuis SDK 2.21.0.
 */
enum QuoteStatus: string
{
    /** Brouillon - editable, pas encore envoye. */
    case Draft = 'draft';

    /** Envoye par email au client. */
    case Sent = 'sent';

    /** Consulte par le client via le lien public. */
    case Viewed = 'viewed';

    /** Accepte par le client (signature canvas posee). */
    case Accepted = 'accepted';

    /** Refuse par le client. */
    case Refused = 'refused';

    /** Expire automatiquement (delai depasse). */
    case Expired = 'expired';

    /** Converti en facture (au moins 1 facture emise). */
    case Converted = 'converted';

    /** Annule par l'emetteur. */
    case Cancelled = 'cancelled';

    /**
     * Indique si le devis peut etre edite (champs/lignes).
     */
    public function canEdit(): bool
    {
        return $this === self::Draft;
    }

    /**
     * Indique si le devis peut etre supprime.
     */
    public function canDelete(): bool
    {
        return in_array($this, [
            self::Draft,
            self::Cancelled,
            self::Refused,
            self::Expired,
        ], true);
    }

    /**
     * Indique si le statut est terminal (ne change plus tout seul).
     */
    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Refused,
            self::Expired,
            self::Converted,
            self::Cancelled,
        ], true);
    }

    /**
     * Indique si le devis peut etre converti en facture.
     */
    public function canConvert(): bool
    {
        return $this === self::Accepted;
    }

    /**
     * Indique si le devis peut etre annule par l'emetteur.
     */
    public function canCancel(): bool
    {
        return in_array($this, [self::Draft, self::Sent, self::Viewed], true);
    }

    /**
     * Libelle francais.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Sent => 'Envoye',
            self::Viewed => 'Consulte',
            self::Accepted => 'Accepte',
            self::Refused => 'Refuse',
            self::Expired => 'Expire',
            self::Converted => 'Converti',
            self::Cancelled => 'Annule',
        };
    }

    /**
     * Couleur Ant Design associee au statut.
     */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'default',
            self::Sent => 'processing',
            self::Viewed => 'cyan',
            self::Accepted => 'success',
            self::Refused => 'error',
            self::Expired => 'warning',
            self::Converted => 'purple',
            self::Cancelled => 'default',
        };
    }
}
