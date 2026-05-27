<?php

declare(strict_types=1);

namespace Scell\Sdk\Enums;

/**
 * Statut KYB (Know Your Business) d'un tenant Scell.io.
 *
 * Aligne sur le check constraint backend `tenants_kyb_status_check`.
 * Le KYB du master tenant est verifie via la plateforme SuperPDP (KYB
 * de l'editeur SaaS). En production, un tenant doit etre `Verified`
 * pour pouvoir emettre des factures B2B en mode electronique.
 *
 * Disponible depuis SDK 2.21.0.
 */
enum TenantKybStatus: string
{
    /** En attente - aucun document soumis. */
    case Pending = 'pending';

    /** Documents soumis, en attente de revue. */
    case DocumentsSubmitted = 'documents_submitted';

    /** Revue humaine en cours. */
    case UnderReview = 'under_review';

    /** KYB verifie - tenant operationnel en production. */
    case Verified = 'verified';

    /** KYB rejete. */
    case Rejected = 'rejected';

    /**
     * Libelle francais.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::DocumentsSubmitted => 'Documents soumis',
            self::UnderReview => 'En revue',
            self::Verified => 'Verifie',
            self::Rejected => 'Rejete',
        };
    }

    /**
     * Indique si le KYB est dans un etat terminal.
     */
    public function isFinal(): bool
    {
        return $this === self::Verified || $this === self::Rejected;
    }

    /**
     * Indique si le tenant peut emettre des factures en production.
     */
    public function canIssueInvoices(): bool
    {
        return $this === self::Verified;
    }
}
