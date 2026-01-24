<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

use DateTimeImmutable;

/**
 * Represente une transaction financiere.
 */
readonly class Transaction
{
    public function __construct(
        public string $id,
        public string $type,
        public float $amount,
        public float $balanceAfter,
        public string $currency,
        public ?string $service = null,
        public ?string $description = null,
        public ?string $referenceId = null,
        public ?string $referenceType = null,
        public ?DateTimeImmutable $createdAt = null,
    ) {}

    /**
     * Cree une instance a partir de la reponse API.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            type: $data['type'],
            amount: (float) $data['amount'],
            balanceAfter: (float) $data['balance_after'],
            currency: $data['currency'] ?? 'EUR',
            service: $data['service'] ?? null,
            description: $data['description'] ?? null,
            referenceId: $data['reference_id'] ?? null,
            referenceType: $data['reference_type'] ?? null,
            createdAt: isset($data['created_at']) ? new DateTimeImmutable($data['created_at']) : null,
        );
    }

    /**
     * Verifie si c'est un debit.
     */
    public function isDebit(): bool
    {
        return $this->type === 'debit';
    }

    /**
     * Verifie si c'est un credit.
     */
    public function isCredit(): bool
    {
        return $this->type === 'credit';
    }

    /**
     * Retourne le montant formate avec signe.
     */
    public function formattedAmount(): string
    {
        $sign = $this->isCredit() ? '+' : '-';
        return $sign . number_format(abs($this->amount), 2, ',', ' ') . ' ' . $this->currency;
    }
}
