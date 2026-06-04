<?php

declare(strict_types=1);

namespace Scell\Sdk\Enums;

/**
 * Statut d'une occurrence (echeance) d'un profil de facturation recurrente.
 *
 * Aligne sur le backend Scell.io.
 *
 * Disponible depuis SDK 2.34.0.
 */
enum RecurringOccurrenceStatus: string
{
    /** En attente : l'echeance n'a pas encore ete traitee. */
    case Pending = 'pending';

    /** Emise : la facture a ete generee (et soumise si emission_mode=auto_send). */
    case Emitted = 'emitted';

    /** Echouee : l'emission a echoue (voir `last_error`). Sera retentee. */
    case Failed = 'failed';

    /** Ignoree : l'echeance a ete sautee (profil en pause au moment du run). */
    case Skipped = 'skipped';

    /**
     * Indique si l'occurrence a produit une facture.
     */
    public function isEmitted(): bool
    {
        return $this === self::Emitted;
    }

    /**
     * Indique si l'occurrence est en erreur.
     */
    public function isFailed(): bool
    {
        return $this === self::Failed;
    }

    /**
     * Libelle francais.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Emitted => 'Emise',
            self::Failed => 'Echouee',
            self::Skipped => 'Ignoree',
        };
    }

    /**
     * Couleur Ant Design associee au statut.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'default',
            self::Emitted => 'success',
            self::Failed => 'error',
            self::Skipped => 'warning',
        };
    }
}
