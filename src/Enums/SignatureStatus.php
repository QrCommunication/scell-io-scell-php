<?php

declare(strict_types=1);

namespace Scell\Sdk\Enums;

/**
 * Statut de la demande de signature.
 */
enum SignatureStatus: string
{
    /** En attente - demande creee */
    case Pending = 'pending';

    /** En attente des signataires */
    case WaitingSigners = 'waiting_signers';

    /** Partiellement signee */
    case PartiallySigned = 'partially_signed';

    /** Terminee - tous les signataires ont signe */
    case Completed = 'completed';

    /** Refusee par un signataire */
    case Refused = 'refused';

    /** Expiree - delai depasse */
    case Expired = 'expired';

    /** Erreur de traitement */
    case Error = 'error';

    /**
     * Retourne le libelle en francais.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::WaitingSigners => 'En attente des signataires',
            self::PartiallySigned => 'Partiellement signee',
            self::Completed => 'Terminee',
            self::Refused => 'Refusee',
            self::Expired => 'Expiree',
            self::Error => 'Erreur',
        };
    }

    /**
     * Verifie si le statut est final.
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::Completed, self::Refused, self::Expired, self::Error]);
    }

    /**
     * Verifie si un rappel peut etre envoye.
     */
    public function canRemind(): bool
    {
        return in_array($this, [self::WaitingSigners, self::PartiallySigned]);
    }
}
