<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

/**
 * Represente le solde d'un compte.
 */
readonly class Balance
{
    public function __construct(
        public float $amount,
        public string $currency,
        public bool $autoReloadEnabled,
        public ?float $autoReloadThreshold = null,
        public ?float $autoReloadAmount = null,
        public float $lowBalanceAlertThreshold = 10.0,
        public float $criticalBalanceAlertThreshold = 5.0,
    ) {}

    /**
     * Cree une instance a partir de la reponse API.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            amount: (float) $data['amount'],
            currency: $data['currency'] ?? 'EUR',
            autoReloadEnabled: $data['auto_reload_enabled'] ?? false,
            autoReloadThreshold: isset($data['auto_reload_threshold']) ? (float) $data['auto_reload_threshold'] : null,
            autoReloadAmount: isset($data['auto_reload_amount']) ? (float) $data['auto_reload_amount'] : null,
            lowBalanceAlertThreshold: (float) ($data['low_balance_alert_threshold'] ?? 10.0),
            criticalBalanceAlertThreshold: (float) ($data['critical_balance_alert_threshold'] ?? 5.0),
        );
    }

    /**
     * Verifie si le solde est bas.
     */
    public function isLow(): bool
    {
        return $this->amount <= $this->lowBalanceAlertThreshold;
    }

    /**
     * Verifie si le solde est critique.
     */
    public function isCritical(): bool
    {
        return $this->amount <= $this->criticalBalanceAlertThreshold;
    }

    /**
     * Verifie si le solde permet une operation.
     */
    public function canAfford(float $amount): bool
    {
        return $this->amount >= $amount;
    }

    /**
     * Retourne le montant formate.
     */
    public function formatted(): string
    {
        return number_format($this->amount, 2, ',', ' ') . ' ' . $this->currency;
    }
}
