<?php

declare(strict_types=1);

namespace Scell\Sdk\Enums;

/**
 * Statut d'une session d'onboarding (widget v3 SubTenant).
 *
 * Aligne sur le check constraint backend `onboarding_sessions_status_check`.
 * Distinct de `OnboardingStatus` (qui suit le cycle de vie complet du
 * SubTenant cote backend). `OnboardingSessionStatus` trace la progression
 * dans le widget JS (`<scell-onboarding>`) etape par etape.
 *
 * Disponible depuis SDK 2.21.0.
 */
enum OnboardingSessionStatus: string
{
    /** Session initiee, aucune etape commencee. */
    case Initiated = 'initiated';

    /** SIRET verifie via Sirene/INSEE. */
    case SiretVerified = 'siret_verified';

    /** Numero de TVA verifie. */
    case VatVerified = 'vat_verified';

    /** Documents en attente de soumission. */
    case DocumentsPending = 'documents_pending';

    /** Documents soumis. */
    case DocumentsSubmitted = 'documents_submitted';

    /** En revue humaine. */
    case UnderReview = 'under_review';

    /** Onboarding complete. */
    case Completed = 'completed';

    /** Echec de l'onboarding. */
    case Failed = 'failed';

    /** Session expiree (delai depasse). */
    case Expired = 'expired';

    /**
     * Libelle francais.
     */
    public function label(): string
    {
        return match ($this) {
            self::Initiated => 'Initiee',
            self::SiretVerified => 'SIRET verifie',
            self::VatVerified => 'TVA verifiee',
            self::DocumentsPending => 'Documents en attente',
            self::DocumentsSubmitted => 'Documents soumis',
            self::UnderReview => 'En revue',
            self::Completed => 'Complete',
            self::Failed => 'Echec',
            self::Expired => 'Expiree',
        };
    }

    /**
     * Indique si la session est dans un etat terminal.
     */
    public function isFinal(): bool
    {
        return in_array($this, [
            self::Completed,
            self::Failed,
            self::Expired,
        ], true);
    }

    /**
     * Indique si la session est encore active (l'utilisateur peut continuer).
     */
    public function isActive(): bool
    {
        return ! $this->isFinal();
    }
}
