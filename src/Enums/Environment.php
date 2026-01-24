<?php

declare(strict_types=1);

namespace Scell\Sdk\Enums;

/**
 * Environnement d'execution.
 */
enum Environment: string
{
    /** Mode test - pas de debit, pas d'envoi reel */
    case Sandbox = 'sandbox';

    /** Mode production - operations reelles et facturees */
    case Production = 'production';

    /**
     * Retourne le libelle en francais.
     */
    public function label(): string
    {
        return match ($this) {
            self::Sandbox => 'Sandbox (test)',
            self::Production => 'Production',
        };
    }

    /**
     * Verifie si c'est le mode sandbox.
     */
    public function isSandbox(): bool
    {
        return $this === self::Sandbox;
    }

    /**
     * Verifie si c'est le mode production.
     */
    public function isProduction(): bool
    {
        return $this === self::Production;
    }
}
