<?php

declare(strict_types=1);

namespace Scell\Sdk\Enums;

/**
 * Mode d'emission des occurrences d'un profil de facturation recurrente.
 *
 * Aligne sur le backend Scell.io.
 *
 * Disponible depuis SDK 2.34.0.
 */
enum RecurringEmissionMode: string
{
    /**
     * Brouillon : a l'echeance, une facture brouillon est creee et reste a
     * valider/soumettre manuellement.
     */
    case Draft = 'draft';

    /**
     * Auto : a l'echeance, la facture est creee puis soumise automatiquement
     * (Factur-X / UBL / CII generes et transmis sans intervention).
     */
    case AutoSend = 'auto_send';

    /**
     * Libelle francais.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::AutoSend => 'Emission automatique',
        };
    }

    /**
     * Indique si les occurrences sont soumises automatiquement.
     */
    public function isAutomatic(): bool
    {
        return $this === self::AutoSend;
    }
}
