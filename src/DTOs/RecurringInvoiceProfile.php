<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

use DateTimeImmutable;

/**
 * Represente un profil de facturation recurrente (abonnement).
 *
 * Un profil decrit un gabarit de facture (buyer, lignes, format) et une
 * cadence d'emission (recurrence). A chaque echeance, le backend genere
 * une occurrence -> une facture (brouillon ou auto-soumise selon
 * `emission_mode`). Le suivi des echeances se fait via les occurrences
 * (voir {@see RecurringInvoiceOccurrence} et
 * `RecurringInvoiceResource::occurrences()`).
 *
 * Comme pour {@see Quote}, le statut et les modes sont exposes en chaines
 * (alignees sur les enums {@see \Scell\Sdk\Enums\RecurringProfileStatus},
 * {@see \Scell\Sdk\Enums\RecurringEmissionMode},
 * {@see \Scell\Sdk\Enums\RecurrenceIntervalUnit} et
 * {@see \Scell\Sdk\Enums\RecurrenceEndMode}).
 *
 * Disponible depuis SDK 2.34.0.
 */
readonly class RecurringInvoiceProfile
{
    /**
     * @param InvoiceLine[] $lines Lignes du gabarit de facture.
     * @param array{
     *     interval_unit: string,
     *     interval_count: int,
     *     day_of_month?: int|null,
     *     day_of_week?: int|null,
     *     human?: string|null
     * } $recurrence Cadence d'emission.
     * @param array{
     *     total_ht: float,
     *     total_tax: float,
     *     total_ttc: float
     * } $totals Totaux calcules du gabarit.
     */
    public function __construct(
        public string $id,
        public string $title,
        /** Statut : active|paused|completed|cancelled. */
        public string $status,
        /** Mode d'emission : draft|auto_send. */
        public string $emissionMode,
        /** Environnement : production|sandbox. */
        public string $environment,
        public string $tenantId,
        public array $recurrence,
        public DateTimeImmutable $startDate,
        /** Mode de fin : never|on_date|after_occurrences. */
        public string $endMode,
        public array $lines,
        public array $totals,
        public ?string $subTenantId = null,
        public ?string $companyId = null,
        public ?string $buyerId = null,
        public ?string $buyerName = null,
        public string $currency = 'EUR',
        /** Format de sortie : facturx|ubl|cii. */
        public ?string $outputFormat = null,
        public ?string $paymentTerms = null,
        public ?DateTimeImmutable $endDate = null,
        public ?int $maxOccurrences = null,
        /** Nombre de jours avant l'echeance ou notifier (rappel). */
        public ?int $notifyBeforeDays = null,
        /** Date/heure de la prochaine emission planifiee. NULL si terminee/annulee. */
        public ?DateTimeImmutable $nextRunAt = null,
        /** Nombre d'occurrences deja generees. */
        public int $occurrencesCount = 0,
        /** Date de la derniere occurrence emise (Y-m-d). NULL si aucune. */
        public ?DateTimeImmutable $lastEmittedOn = null,
        public ?DateTimeImmutable $createdAt = null,
        public ?DateTimeImmutable $updatedAt = null,
    ) {}

    /**
     * Cree une instance a partir de la reponse API.
     */
    public static function fromArray(array $data): self
    {
        $lines = array_map(
            fn(array $line) => InvoiceLine::fromArray($line),
            $data['lines'] ?? []
        );

        $recurrence = is_array($data['recurrence'] ?? null) ? $data['recurrence'] : [];
        $recurrence = [
            'interval_unit' => $recurrence['interval_unit'] ?? 'month',
            'interval_count' => (int) ($recurrence['interval_count'] ?? 1),
            'day_of_month' => $recurrence['day_of_month'] ?? null,
            'day_of_week' => $recurrence['day_of_week'] ?? null,
            'human' => $recurrence['human'] ?? null,
        ];

        $totals = is_array($data['totals'] ?? null) ? $data['totals'] : [];
        $totals = [
            'total_ht' => (float) ($totals['total_ht'] ?? 0),
            'total_tax' => (float) ($totals['total_tax'] ?? 0),
            'total_ttc' => (float) ($totals['total_ttc'] ?? 0),
        ];

        return new self(
            id: $data['id'],
            title: $data['title'],
            status: $data['status'] ?? 'active',
            emissionMode: $data['emission_mode'] ?? 'draft',
            environment: $data['environment'] ?? 'production',
            tenantId: $data['tenant_id'],
            recurrence: $recurrence,
            startDate: new DateTimeImmutable($data['start_date']),
            endMode: $data['end_mode'] ?? 'never',
            lines: $lines,
            totals: $totals,
            subTenantId: $data['sub_tenant_id'] ?? null,
            companyId: $data['company_id'] ?? null,
            buyerId: $data['buyer_id'] ?? null,
            buyerName: $data['buyer_name'] ?? null,
            currency: $data['currency'] ?? 'EUR',
            outputFormat: $data['output_format'] ?? null,
            paymentTerms: $data['payment_terms'] ?? null,
            endDate: isset($data['end_date']) ? new DateTimeImmutable($data['end_date']) : null,
            maxOccurrences: isset($data['max_occurrences']) ? (int) $data['max_occurrences'] : null,
            notifyBeforeDays: isset($data['notify_before_days']) ? (int) $data['notify_before_days'] : null,
            nextRunAt: isset($data['next_run_at']) ? new DateTimeImmutable($data['next_run_at']) : null,
            occurrencesCount: (int) ($data['occurrences_count'] ?? 0),
            lastEmittedOn: isset($data['last_emitted_on']) ? new DateTimeImmutable($data['last_emitted_on']) : null,
            createdAt: isset($data['created_at']) ? new DateTimeImmutable($data['created_at']) : null,
            updatedAt: isset($data['updated_at']) ? new DateTimeImmutable($data['updated_at']) : null,
        );
    }

    /**
     * Indique si le profil est actif (emet a l'echeance).
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Indique si le profil est en pause.
     */
    public function isPaused(): bool
    {
        return $this->status === 'paused';
    }

    /**
     * Indique si le profil est termine (fin de recurrence atteinte).
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Indique si le profil a ete annule.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Indique si les occurrences sont soumises automatiquement (auto_send).
     */
    public function isAutoSend(): bool
    {
        return $this->emissionMode === 'auto_send';
    }

    /**
     * Indique si le profil est en mode sandbox.
     */
    public function isSandbox(): bool
    {
        return $this->environment === 'sandbox';
    }
}
