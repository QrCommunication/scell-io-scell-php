<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

use DateTimeImmutable;

/**
 * Represente une occurrence (echeance) d'un profil de facturation recurrente.
 *
 * Chaque echeance du profil produit une occurrence. Quand elle est traitee
 * avec succes, elle reference la facture generee (`invoice_id` /
 * `invoice_number`). En cas d'echec, `last_error` porte le motif et
 * l'occurrence sera retentee.
 *
 * Le statut est expose en chaine (alignee sur l'enum
 * {@see \Scell\Sdk\Enums\RecurringOccurrenceStatus}).
 *
 * Disponible depuis SDK 2.34.0.
 */
readonly class RecurringInvoiceOccurrence
{
    public function __construct(
        public string $id,
        public string $recurringProfileId,
        /** Numero d'ordre de l'occurrence dans le profil (1-based). */
        public int $occurrenceNumber,
        public DateTimeImmutable $occurrenceDate,
        /** Statut : pending|emitted|failed|skipped. */
        public string $status,
        public ?string $invoiceId = null,
        public ?string $invoiceNumber = null,
        /** Nombre de tentatives d'emission. */
        public int $attempts = 0,
        public ?string $lastError = null,
        public ?DateTimeImmutable $emittedAt = null,
        public ?DateTimeImmutable $failedAt = null,
        public ?DateTimeImmutable $createdAt = null,
    ) {}

    /**
     * Cree une instance a partir de la reponse API.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            recurringProfileId: $data['recurring_profile_id'],
            occurrenceNumber: (int) ($data['occurrence_number'] ?? 0),
            occurrenceDate: new DateTimeImmutable($data['occurrence_date']),
            status: $data['status'] ?? 'pending',
            invoiceId: $data['invoice_id'] ?? null,
            invoiceNumber: $data['invoice_number'] ?? null,
            attempts: (int) ($data['attempts'] ?? 0),
            lastError: $data['last_error'] ?? null,
            emittedAt: isset($data['emitted_at']) ? new DateTimeImmutable($data['emitted_at']) : null,
            failedAt: isset($data['failed_at']) ? new DateTimeImmutable($data['failed_at']) : null,
            createdAt: isset($data['created_at']) ? new DateTimeImmutable($data['created_at']) : null,
        );
    }

    /**
     * Indique si l'occurrence a produit une facture.
     */
    public function isEmitted(): bool
    {
        return $this->status === 'emitted';
    }

    /**
     * Indique si l'occurrence est en attente de traitement.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Indique si l'occurrence est en erreur.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Indique si l'occurrence a ete ignoree (profil en pause au moment du run).
     */
    public function isSkipped(): bool
    {
        return $this->status === 'skipped';
    }
}
