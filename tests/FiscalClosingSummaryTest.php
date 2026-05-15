<?php

declare(strict_types=1);

namespace Scell\Sdk\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Scell\Sdk\DTOs\FiscalClosingSummary;

class FiscalClosingSummaryTest extends TestCase
{
    #[Test]
    public function hydrates_full_payload_from_api_response(): void
    {
        $payload = [
            'id' => '019ee000-1234-7890-abcd-1234567890ab',
            'tenant_id' => '019ee000-aaaa-bbbb-cccc-111122223333',
            'sub_tenant_id' => null,
            'closing_date' => '2026-05-14',
            'closing_type' => 'daily',
            'status' => 'closed',
            'entries_count' => 12,
            'first_sequence_number' => 1001,
            'last_sequence_number' => 1012,
            'closing_hash' => str_repeat('a', 64),
            'previous_closing_hash' => str_repeat('b', 64),
            'totals' => ['invoices_count' => 12, 'invoices_total_ttc' => 1234.56, 'credit_notes_total' => 50.0],
            'cumulative_totals' => ['total_ht' => 999.99],
            'environment' => 'production',
            'csv_path' => 's3://fiscal/2026/05/14.csv',
            'csv_hash' => str_repeat('c', 64),
            'ots_proof_base64' => base64_encode("OpenTimestamps\x00\x00Proof\x00\xbf\x89\xe2\xe8"),
            'ots_status' => 'pending',
            'ots_submitted_at' => '2026-05-14T00:05:00+00:00',
            'ots_bitcoin_confirmed_at' => null,
            'ots_calendars' => [
                ['calendar' => 'https://alice.btc.calendar.opentimestamps.org', 'ok' => true],
            ],
            'metadata' => ['emailed_at' => '2026-05-14T00:06:12+00:00'],
            'created_at' => '2026-05-14T00:05:00+00:00',
        ];

        $closing = FiscalClosingSummary::fromArray($payload);

        $this->assertSame($payload['id'], $closing->id);
        $this->assertSame('daily', $closing->closingType);
        $this->assertSame('closed', $closing->status);
        $this->assertTrue($closing->isClosed());
        $this->assertSame(12, $closing->entriesCount);
        $this->assertSame(1001, $closing->firstSequenceNumber);
        $this->assertSame(1012, $closing->lastSequenceNumber);
        $this->assertSame($payload['closing_hash'], $closing->closingHash);
        $this->assertSame($payload['closing_hash'], $closing->chainHash);
        $this->assertSame($payload['previous_closing_hash'], $closing->previousClosingHash);
        $this->assertSame($payload['totals'], $closing->totals);
        $this->assertSame(1234.56, $closing->totalDebit);
        $this->assertSame(50.0, $closing->totalCredit);
        $this->assertSame($payload['cumulative_totals'], $closing->cumulativeTotals);
        $this->assertSame('production', $closing->environment);
        $this->assertSame($payload['csv_path'], $closing->csvPath);
    }

    #[Test]
    public function ots_proof_base64_round_trips_to_original_binary(): void
    {
        $rawOts = "OpenTimestamps\x00\x00Proof\x00\xbf\x89\xe2\xe8\x84\xe8\x92\x94"
            .random_bytes(64);

        $closing = FiscalClosingSummary::fromArray([
            'id' => 'id',
            'tenant_id' => 'tenant',
            'closing_date' => '2026-05-14',
            'closing_type' => 'daily',
            'status' => 'closed',
            'entries_count' => 0,
            'ots_proof_base64' => base64_encode($rawOts),
            'ots_status' => 'pending',
        ]);

        $this->assertTrue($closing->hasOtsProof());
        $this->assertSame($rawOts, $closing->decodeOtsProof());
    }

    #[Test]
    public function ots_helpers_return_null_when_no_proof_attached(): void
    {
        $closing = FiscalClosingSummary::fromArray([
            'id' => 'id',
            'tenant_id' => 'tenant',
            'closing_date' => '2026-05-14',
            'closing_type' => 'daily',
            'status' => 'closed',
            'entries_count' => 0,
        ]);

        $this->assertFalse($closing->hasOtsProof());
        $this->assertNull($closing->decodeOtsProof());
        $this->assertNull($closing->otsStatus);
    }

    #[Test]
    public function legacy_payload_with_chain_hash_only_still_hydrates(): void
    {
        // Pre-v2.10.0 some API versions exposed only chain_hash, no
        // closing_hash. Ensure we don't blow up on those.
        $closing = FiscalClosingSummary::fromArray([
            'id' => 'id',
            'tenant_id' => 'tenant',
            'closing_date' => '2026-05-14',
            'closing_type' => 'daily',
            'status' => 'closed',
            'entries_count' => 5,
            'chain_hash' => str_repeat('d', 64),
            'total_debit' => 100.0,
            'total_credit' => 25.0,
        ]);

        $this->assertSame(str_repeat('d', 64), $closing->chainHash);
        $this->assertNull($closing->closingHash);
        $this->assertSame(100.0, $closing->totalDebit);
        $this->assertSame(25.0, $closing->totalCredit);
    }
}
