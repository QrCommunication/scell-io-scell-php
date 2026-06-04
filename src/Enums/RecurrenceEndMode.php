<?php

declare(strict_types=1);

namespace Scell\Sdk\Enums;

/**
 * Mode de fin d'un profil de facturation recurrente.
 *
 * Aligne sur le backend Scell.io.
 *
 * Disponible depuis SDK 2.34.0.
 */
enum RecurrenceEndMode: string
{
    /** Jamais : le profil emet indefiniment jusqu'a pause/annulation manuelle. */
    case Never = 'never';

    /** S'arrete a une date donnee (`end_date`). */
    case OnDate = 'on_date';

    /** S'arrete apres un nombre d'occurrences emises (`max_occurrences`). */
    case AfterOccurrences = 'after_occurrences';

    /**
     * Libelle francais.
     */
    public function label(): string
    {
        return match ($this) {
            self::Never => 'Sans fin',
            self::OnDate => 'A une date',
            self::AfterOccurrences => 'Apres N occurrences',
        };
    }
}
