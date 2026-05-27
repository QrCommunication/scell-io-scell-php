<?php

declare(strict_types=1);

namespace Scell\Sdk\Enums;

/**
 * Statut d'archivage d'une signature electronique sur S3 / Glacier.
 *
 * Aligne sur le check constraint backend `signatures_archive_status_check`.
 * Cycle d'archivage des PDFs signes (preuve eIDAS EU-SES) :
 *
 *   pending   -> archived  (S3 Standard, lock COMPLIANCE 11 ans)
 *             -> archived  -> glacier  (apres 90 jours)
 *
 * Disponible depuis SDK 2.21.0.
 */
enum SignatureArchiveStatus: string
{
    /** En attente d'archivage. */
    case Pending = 'pending';

    /** Archive sur S3 Standard (Object Lock COMPLIANCE 11 ans). */
    case Archived = 'archived';

    /** Bascule sur S3 Glacier Deep Archive (apres 90 jours). */
    case Glacier = 'glacier';

    /** Erreur lors de l'archivage. */
    case Error = 'error';

    /**
     * Libelle francais.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Archived => 'Archive',
            self::Glacier => 'Glacier',
            self::Error => 'Erreur',
        };
    }

    /**
     * Indique si la signature est archivee de maniere durable.
     */
    public function isArchived(): bool
    {
        return $this === self::Archived || $this === self::Glacier;
    }
}
