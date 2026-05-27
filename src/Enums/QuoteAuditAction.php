<?php

declare(strict_types=1);

namespace Scell\Sdk\Enums;

/**
 * Action enregistree dans l'audit log d'un devis (chain SHA-256 append-only).
 *
 * Aligne sur `App\Enums\Quote\QuoteAuditAction` du backend Scell.io.
 * L'audit log des devis est HORS chaine ISCA : chaine SHA-256 separee,
 * isolation stricte par (tenant, sub_tenant).
 *
 * Disponible depuis SDK 2.21.0.
 */
enum QuoteAuditAction: string
{
    /** Devis cree. */
    case Created = 'created';

    /** Champs du devis modifies. */
    case Updated = 'updated';

    /** Ligne ajoutee au devis. */
    case LineAdded = 'line_added';

    /** Ligne supprimee du devis. */
    case LineRemoved = 'line_removed';

    /** Ligne modifiee. */
    case LineUpdated = 'line_updated';

    /** Acheteur change. */
    case BuyerChanged = 'buyer_changed';

    /** Devis envoye par email au client. */
    case Sent = 'sent';

    /** Devis renvoye par email (deuxieme envoi+). */
    case Resent = 'resent';

    /** Devis consulte via le lien public (1ere ouverture). */
    case Viewed = 'viewed';

    /** Signature canvas posee par le client. */
    case Signed = 'signed';

    /** Devis accepte par le client. */
    case Accepted = 'accepted';

    /** Devis refuse par le client. */
    case Refused = 'refused';

    /** Devis annule par l'emetteur. */
    case Cancelled = 'cancelled';

    /** Devis expire automatiquement (delai depasse). */
    case Expired = 'expired';

    /** Devis converti en facture. */
    case Converted = 'converted';

    /** Lien public regenere (nouveau token signe). */
    case PublicLinkRegenerated = 'public_link_regenerated';

    /** Lien public revoque (token invalide). */
    case PublicLinkRevoked = 'public_link_revoked';

    /** Devis duplique. */
    case Duplicated = 'duplicated';

    /** Facture d'acompte generee automatiquement depuis une ligne d'echeancier. */
    case DepositGeneratedFromSchedule = 'deposit_generated_from_schedule';

    /** Lignes d'echeancier mises a jour en bloc. */
    case ScheduleUpdated = 'schedule_updated';

    /** Lignes d'echeancier supprimees. */
    case ScheduleDeleted = 'schedule_deleted';

    /**
     * Libelle francais.
     */
    public function label(): string
    {
        return match ($this) {
            self::Created => 'Cree',
            self::Updated => 'Modifie',
            self::LineAdded => 'Ligne ajoutee',
            self::LineRemoved => 'Ligne supprimee',
            self::LineUpdated => 'Ligne modifiee',
            self::BuyerChanged => 'Acheteur change',
            self::Sent => 'Envoye',
            self::Resent => 'Renvoye',
            self::Viewed => 'Consulte',
            self::Signed => 'Signe',
            self::Accepted => 'Accepte',
            self::Refused => 'Refuse',
            self::Cancelled => 'Annule',
            self::Expired => 'Expire',
            self::Converted => 'Converti',
            self::PublicLinkRegenerated => 'Lien public regenere',
            self::PublicLinkRevoked => 'Lien public revoque',
            self::Duplicated => 'Duplique',
            self::DepositGeneratedFromSchedule => 'Acompte genere (echeancier)',
            self::ScheduleUpdated => 'Echeancier mis a jour',
            self::ScheduleDeleted => 'Echeancier supprime',
        };
    }

    /**
     * Indique si l'action modifie l'etat du devis (vs simple consultation).
     */
    public function isMutation(): bool
    {
        return $this !== self::Viewed;
    }

    /**
     * Indique si l'action est une transition d'etat (lifecycle).
     */
    public function isStateTransition(): bool
    {
        return in_array($this, [
            self::Sent,
            self::Viewed,
            self::Accepted,
            self::Refused,
            self::Cancelled,
            self::Expired,
            self::Converted,
        ], true);
    }

    /**
     * Indique si l'action est une modification structurelle du devis.
     */
    public function isModification(): bool
    {
        return in_array($this, [
            self::Created,
            self::Updated,
            self::LineAdded,
            self::LineRemoved,
            self::LineUpdated,
            self::BuyerChanged,
        ], true);
    }
}
