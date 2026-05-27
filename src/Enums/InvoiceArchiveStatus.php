<?php

declare(strict_types=1);

namespace Scell\Sdk\Enums;

/**
 * Statut d'archivage d'une facture (Factur-X/UBL/CII) sur S3 / Glacier.
 *
 * Aligne sur le check constraint backend `invoices_archive_status_check`.
 * Cycle d'archivage des PDFs/XML factures (conservation fiscale 10 ans
 * Art. L102B Livre des procedures fiscales) :
 *
 *   pending   -> archived  (S3 Standard, lock COMPLIANCE 10 ans)
 *             -> archived  -> glacier  (apres 90 jours)
 *
 * Disponible depuis SDK 2.21.0.
 */
enum InvoiceArchiveStatus: string
{
    /** En attente d'archivage. */
    case Pending = 'pending';

    /** Archive sur S3 Standard (Object Lock COMPLIANCE 10 ans). */
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
     * Indique si la facture est archivee de maniere durable.
     */
    public function isArchived(): bool
    {
        return $this === self::Archived || $this === self::Glacier;
    }
}
