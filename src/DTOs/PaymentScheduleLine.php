<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

use DateTimeImmutable;

/**
 * Represente une ligne d'echeancier de paiement d'un devis.
 *
 * Une ligne porte soit un montant en pourcentage soit un montant fixe,
 * et est declenchee par une date ou un jalon textuel.
 *
 * Statuts possibles : 'pending' | 'invoiced' | 'cancelled'
 */
final readonly class PaymentScheduleLine
{
    public function __construct(
        public string $id,
        public string $quoteId,
        public int $order,
        /** 'percent' ou 'amount' */
        public string $amountType,
        public float $amountValue,
        /** Montant TTC calcule (fige a la signature du devis) */
        public ?float $amountTtcSnapshot,
        /** 'pending' | 'invoiced' | 'cancelled' */
        public string $status,
        public bool $autoGenerate,
        public ?DateTimeImmutable $dueDate,
        public ?string $milestoneLabel,
        public ?string $description,
        public ?string $invoiceId,
        public ?DateTimeImmutable $invoicedAt,
        public ?DateTimeImmutable $lockedAt,
        public ?DateTimeImmutable $reminderTenantSentAt,
        public ?DateTimeImmutable $reminderBuyerSentAt,
        public ?DateTimeImmutable $overdueAlertedAt,
        public ?DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $updatedAt,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            quoteId: $data['quote_id'],
            order: (int) $data['order'],
            amountType: $data['amount_type'],
            amountValue: (float) $data['amount_value'],
            amountTtcSnapshot: isset($data['amount_ttc_snapshot'])
                ? (float) $data['amount_ttc_snapshot']
                : null,
            status: $data['status'] ?? 'pending',
            autoGenerate: (bool) ($data['auto_generate'] ?? false),
            dueDate: isset($data['due_date'])
                ? new DateTimeImmutable($data['due_date'])
                : null,
            milestoneLabel: $data['milestone_label'] ?? null,
            description: $data['description'] ?? null,
            invoiceId: $data['invoice_id'] ?? null,
            invoicedAt: isset($data['invoiced_at'])
                ? new DateTimeImmutable($data['invoiced_at'])
                : null,
            lockedAt: isset($data['locked_at'])
                ? new DateTimeImmutable($data['locked_at'])
                : null,
            reminderTenantSentAt: isset($data['reminder_tenant_sent_at'])
                ? new DateTimeImmutable($data['reminder_tenant_sent_at'])
                : null,
            reminderBuyerSentAt: isset($data['reminder_buyer_sent_at'])
                ? new DateTimeImmutable($data['reminder_buyer_sent_at'])
                : null,
            overdueAlertedAt: isset($data['overdue_alerted_at'])
                ? new DateTimeImmutable($data['overdue_alerted_at'])
                : null,
            createdAt: isset($data['created_at'])
                ? new DateTimeImmutable($data['created_at'])
                : null,
            updatedAt: isset($data['updated_at'])
                ? new DateTimeImmutable($data['updated_at'])
                : null,
        );
    }

    /** Indique si la ligne est en attente de facturation. */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /** Indique si la ligne a ete convertie en facture. */
    public function isInvoiced(): bool
    {
        return $this->status === 'invoiced';
    }

    /** Indique si la ligne est annulee. */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /** Indique si la ligne est verrouillee (devis signe). */
    public function isLocked(): bool
    {
        return $this->lockedAt !== null;
    }

    /** Indique si la ligne est en retard (due date depassee et non facturee). */
    public function isOverdue(): bool
    {
        return $this->isPending()
            && $this->dueDate !== null
            && $this->dueDate < new DateTimeImmutable();
    }
}
