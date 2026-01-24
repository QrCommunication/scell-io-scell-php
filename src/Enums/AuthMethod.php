<?php

declare(strict_types=1);

namespace Scell\Sdk\Enums;

/**
 * Methode d'authentification du signataire.
 */
enum AuthMethod: string
{
    /** Authentification par email (OTP) */
    case Email = 'email';

    /** Authentification par SMS (OTP) */
    case Sms = 'sms';

    /** Double authentification email + SMS */
    case Both = 'both';

    /**
     * Retourne le libelle en francais.
     */
    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email (OTP)',
            self::Sms => 'SMS (OTP)',
            self::Both => 'Email + SMS',
        };
    }

    /**
     * Verifie si un email est requis.
     */
    public function requiresEmail(): bool
    {
        return in_array($this, [self::Email, self::Both]);
    }

    /**
     * Verifie si un telephone est requis.
     */
    public function requiresPhone(): bool
    {
        return in_array($this, [self::Sms, self::Both]);
    }
}
