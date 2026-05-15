<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

/**
 * DTO representing a fiscal closing record.
 *
 * Returned by `GET /api/v1/tenant/fiscal/closings`. Each closing seals a
 * period of the immutable ledger with a SHA-256 `closingHash` chained to
 * the previous closing.
 *
 * @since 2.10.0 OTS metadata fields added (`otsProofBase64`, `otsStatus`,
 *               `otsSubmittedAt`, `otsBitcoinConfirmedAt`, `otsCalendars`).
 *               The raw binary `ots_proof` column (BYTEA, non-UTF8) used to
 *               make `json_encode()` crash with `Type is not supported` and
 *               triggered a 500 on this endpoint; the API now exposes the
 *               OpenTimestamps receipt encoded in base64.
 */
readonly class FiscalClosingSummary
{
    /**
     * @param  array<string, mixed>|null  $totals
     * @param  array<string, mixed>|null  $cumulativeTotals
     * @param  array<int, array<string, mixed>>|null  $otsCalendars
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $closingDate,
        public string $closingType,
        public string $status,
        public int $entriesCount,
        public float $totalDebit,
        public float $totalCredit,
        public ?string $chainHash = null,
        public ?string $environment = null,
        public ?string $createdAt = null,
        public ?string $subTenantId = null,
        public ?int $firstSequenceNumber = null,
        public ?int $lastSequenceNumber = null,
        public ?string $closingHash = null,
        public ?string $previousClosingHash = null,
        public ?array $totals = null,
        public ?array $cumulativeTotals = null,
        public ?string $csvPath = null,
        public ?string $csvHash = null,
        public ?string $otsProofBase64 = null,
        public ?string $otsStatus = null,
        public ?string $otsSubmittedAt = null,
        public ?string $otsBitcoinConfirmedAt = null,
        public ?array $otsCalendars = null,
        public ?array $metadata = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        // Totals shape from API: {invoices_total_ht, invoices_total_ttc, ...}.
        // We surface them as floats; kept here for backward compat with old
        // SDK consumers that read totalDebit/totalCredit.
        $totals = is_array($data['totals'] ?? null) ? $data['totals'] : null;
        $totalDebit = (float) ($data['total_debit']
            ?? ($totals['invoices_total_ttc'] ?? 0));
        $totalCredit = (float) ($data['total_credit']
            ?? ($totals['credit_notes_total'] ?? 0));

        return new self(
            id: (string) $data['id'],
            tenantId: (string) $data['tenant_id'],
            closingDate: (string) $data['closing_date'],
            closingType: (string) ($data['closing_type'] ?? 'daily'),
            status: (string) ($data['status'] ?? 'closed'),
            entriesCount: (int) ($data['entries_count'] ?? 0),
            totalDebit: $totalDebit,
            totalCredit: $totalCredit,
            chainHash: $data['chain_hash'] ?? ($data['closing_hash'] ?? null),
            environment: $data['environment'] ?? null,
            createdAt: $data['created_at'] ?? null,
            subTenantId: $data['sub_tenant_id'] ?? null,
            firstSequenceNumber: isset($data['first_sequence_number'])
                ? (int) $data['first_sequence_number'] : null,
            lastSequenceNumber: isset($data['last_sequence_number'])
                ? (int) $data['last_sequence_number'] : null,
            closingHash: $data['closing_hash'] ?? null,
            previousClosingHash: $data['previous_closing_hash'] ?? null,
            totals: $totals,
            cumulativeTotals: is_array($data['cumulative_totals'] ?? null)
                ? $data['cumulative_totals'] : null,
            csvPath: $data['csv_path'] ?? null,
            csvHash: $data['csv_hash'] ?? null,
            otsProofBase64: $data['ots_proof_base64'] ?? null,
            otsStatus: $data['ots_status'] ?? null,
            otsSubmittedAt: $data['ots_submitted_at'] ?? null,
            otsBitcoinConfirmedAt: $data['ots_bitcoin_confirmed_at'] ?? null,
            otsCalendars: is_array($data['ots_calendars'] ?? null)
                ? $data['ots_calendars'] : null,
            metadata: is_array($data['metadata'] ?? null)
                ? $data['metadata'] : null,
        );
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    /**
     * True when an OpenTimestamps receipt has been produced (regardless of
     * Bitcoin confirmation status).
     */
    public function hasOtsProof(): bool
    {
        return $this->otsProofBase64 !== null && $this->otsProofBase64 !== '';
    }

    /**
     * Decode the OpenTimestamps receipt back to its raw binary form. Returns
     * `null` if no proof is attached. The raw bytes can be written to a
     * `.ots` file and verified with the `ots` CLI or any OpenTimestamps
     * library.
     */
    public function decodeOtsProof(): ?string
    {
        if (! $this->hasOtsProof()) {
            return null;
        }
        $binary = base64_decode($this->otsProofBase64, true);

        return $binary === false ? null : $binary;
    }
}
