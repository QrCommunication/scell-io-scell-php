<?php

declare(strict_types=1);

namespace Scell\Sdk\Exceptions;

/**
 * Exception levee lorsque le solde est insuffisant.
 */
class InsufficientBalanceException extends ScellException
{
    /**
     * Solde actuel.
     */
    protected ?float $currentBalance = null;

    /**
     * Montant requis.
     */
    protected ?float $requiredAmount = null;

    /**
     * Cree une instance de InsufficientBalanceException.
     */
    public function __construct(
        string $message = 'Solde insuffisant pour effectuer cette operation',
        ?float $currentBalance = null,
        ?float $requiredAmount = null,
        ?array $responseBody = null
    ) {
        parent::__construct($message, 402, null, 'INSUFFICIENT_BALANCE', $responseBody, 402);
        $this->currentBalance = $currentBalance;
        $this->requiredAmount = $requiredAmount;
    }

    /**
     * Retourne le solde actuel.
     */
    public function getCurrentBalance(): ?float
    {
        return $this->currentBalance;
    }

    /**
     * Retourne le montant requis.
     */
    public function getRequiredAmount(): ?float
    {
        return $this->requiredAmount;
    }

    /**
     * Retourne le montant manquant.
     */
    public function getMissingAmount(): ?float
    {
        if ($this->currentBalance === null || $this->requiredAmount === null) {
            return null;
        }

        return max(0, $this->requiredAmount - $this->currentBalance);
    }
}
