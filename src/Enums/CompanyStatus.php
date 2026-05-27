<?php

declare(strict_types=1);

namespace Scell\Sdk\Enums;

/**
 * Statut d'une Company (entite emettrice de factures).
 *
 * Aligne sur le check constraint backend `companies_status_check`.
 * Une Company doit etre `Active` (KYC complet) pour emettre des factures
 * en production. En sandbox, le check est skip pour faciliter le dev.
 *
 * Disponible depuis SDK 2.21.0.
 */
enum CompanyStatus: string
{
    /** En attente - KYC de la company a completer. */
    case PendingKyc = 'pending_kyc';

    /** Active - KYC complet, peut emettre des factures. */
    case Active = 'active';

    /** Suspendue - emission des factures bloquee. */
    case Suspended = 'suspended';

    /**
     * Libelle francais.
     */
    public function label(): string
    {
        return match ($this) {
            self::PendingKyc => 'En attente KYC',
            self::Active => 'Active',
            self::Suspended => 'Suspendue',
        };
    }

    /**
     * Indique si la company peut emettre des factures.
     */
    public function canIssueInvoices(): bool
    {
        return $this === self::Active;
    }
}
