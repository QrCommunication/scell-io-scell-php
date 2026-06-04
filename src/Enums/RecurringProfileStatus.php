<?php

declare(strict_types=1);

namespace Scell\Sdk\Enums;

/**
 * Statut d'un profil de facturation recurrente dans son cycle de vie.
 *
 * Aligne sur le backend Scell.io.
 *
 * Cycle nominal :
 *   active <-> paused
 *   active -> completed (fin atteinte : end_date ou max_occurrences)
 *   active/paused -> cancelled (annulation manuelle, terminal)
 *
 * Disponible depuis SDK 2.34.0.
 */
enum RecurringProfileStatus: string
{
    /** Actif : les occurrences sont emises a l'echeance. */
    case Active = 'active';

    /** En pause : aucune occurrence emise tant que non reactive. */
    case Paused = 'paused';

    /** Termine : la fin de recurrence a ete atteinte (date ou max occurrences). */
    case Completed = 'completed';

    /** Annule par l'emetteur (terminal). */
    case Cancelled = 'cancelled';

    /**
     * Indique si le profil peut etre mis en pause.
     */
    public function canPause(): bool
    {
        return $this === self::Active;
    }

    /**
     * Indique si le profil peut etre reactive.
     */
    public function canActivate(): bool
    {
        return $this === self::Paused;
    }

    /**
     * Indique si le profil peut etre annule.
     */
    public function canCancel(): bool
    {
        return in_array($this, [self::Active, self::Paused], true);
    }

    /**
     * Indique si le statut est terminal (ne change plus tout seul).
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }

    /**
     * Libelle francais.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => 'Actif',
            self::Paused => 'En pause',
            self::Completed => 'Termine',
            self::Cancelled => 'Annule',
        };
    }

    /**
     * Couleur Ant Design associee au statut.
     */
    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Paused => 'warning',
            self::Completed => 'purple',
            self::Cancelled => 'default',
        };
    }
}
