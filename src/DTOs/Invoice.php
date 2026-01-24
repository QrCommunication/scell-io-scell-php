<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

use DateTimeImmutable;
use Scell\Sdk\Enums\Direction;
use Scell\Sdk\Enums\Environment;
use Scell\Sdk\Enums\InvoiceStatus;
use Scell\Sdk\Enums\OutputFormat;

/**
 * Represente une facture electronique.
 */
readonly class Invoice
{
    /**
     * @param InvoiceLine[] $lines
     */
    public function __construct(
        public string $id,
        public string $invoiceNumber,
        public Direction $direction,
        public OutputFormat $outputFormat,
        public DateTimeImmutable $issueDate,
        public float $totalHt,
        public float $totalTax,
        public float $totalTtc,
        public string $sellerSiret,
        public string $sellerName,
        public Address $sellerAddress,
        public string $buyerSiret,
        public string $buyerName,
        public Address $buyerAddress,
        public array $lines,
        public InvoiceStatus $status,
        public Environment $environment,
        public ?string $externalId = null,
        public ?DateTimeImmutable $dueDate = null,
        public string $currency = 'EUR',
        public ?string $statusMessage = null,
        public bool $archiveEnabled = false,
        public ?float $amountCharged = null,
        public ?DateTimeImmutable $createdAt = null,
        public ?DateTimeImmutable $validatedAt = null,
        public ?DateTimeImmutable $transmittedAt = null,
        public ?DateTimeImmutable $completedAt = null,
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

        return new self(
            id: $data['id'],
            invoiceNumber: $data['invoice_number'],
            direction: Direction::from($data['direction']),
            outputFormat: OutputFormat::from($data['output_format']),
            issueDate: new DateTimeImmutable($data['issue_date']),
            totalHt: (float) $data['total_ht'],
            totalTax: (float) $data['total_tax'],
            totalTtc: (float) $data['total_ttc'],
            sellerSiret: $data['seller']['siret'],
            sellerName: $data['seller']['name'],
            sellerAddress: Address::fromArray($data['seller']['address']),
            buyerSiret: $data['buyer']['siret'],
            buyerName: $data['buyer']['name'],
            buyerAddress: Address::fromArray($data['buyer']['address']),
            lines: $lines,
            status: InvoiceStatus::from($data['status']),
            environment: Environment::from($data['environment']),
            externalId: $data['external_id'] ?? null,
            dueDate: isset($data['due_date']) ? new DateTimeImmutable($data['due_date']) : null,
            currency: $data['currency'] ?? 'EUR',
            statusMessage: $data['status_message'] ?? null,
            archiveEnabled: $data['archive_enabled'] ?? false,
            amountCharged: isset($data['amount_charged']) ? (float) $data['amount_charged'] : null,
            createdAt: isset($data['created_at']) ? new DateTimeImmutable($data['created_at']) : null,
            validatedAt: isset($data['validated_at']) ? new DateTimeImmutable($data['validated_at']) : null,
            transmittedAt: isset($data['transmitted_at']) ? new DateTimeImmutable($data['transmitted_at']) : null,
            completedAt: isset($data['completed_at']) ? new DateTimeImmutable($data['completed_at']) : null,
        );
    }

    /**
     * Verifie si la facture est en mode sandbox.
     */
    public function isSandbox(): bool
    {
        return $this->environment->isSandbox();
    }

    /**
     * Verifie si la facture est une facture de vente.
     */
    public function isOutgoing(): bool
    {
        return $this->direction === Direction::Outgoing;
    }

    /**
     * Verifie si le statut est final.
     */
    public function isFinal(): bool
    {
        return $this->status->isFinal();
    }
}
