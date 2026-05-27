<?php

declare(strict_types=1);

namespace Scell\Sdk\Enums;

/**
 * Statut d'une cle API (sk_live_* / sk_test_* / pk_*).
 *
 * Aligne sur le check constraint backend `api_keys_status_check`.
 * Une cle revoquee n'est plus utilisable mais reste tracee en DB
 * pour l'audit (qui, quand, pourquoi).
 *
 * Disponible depuis SDK 2.21.0.
 */
enum ApiKeyStatus: string
{
    /** Active - cle utilisable. */
    case Active = 'active';

    /** Revoquee - ne plus utiliser (audit trail conserve). */
    case Revoked = 'revoked';

    /**
     * Libelle francais.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Revoked => 'Revoquee',
        };
    }

    /**
     * Indique si la cle est utilisable.
     */
    public function isUsable(): bool
    {
        return $this === self::Active;
    }
}
