<?php

declare(strict_types=1);

namespace Scell\Sdk\Resources;

use Scell\Sdk\DTOs\FiscalAnchor;
use Scell\Sdk\DTOs\FiscalAttestation;
use Scell\Sdk\DTOs\FiscalClosingSummary;
use Scell\Sdk\DTOs\FiscalCompliance;
use Scell\Sdk\DTOs\FiscalEntry;
use Scell\Sdk\DTOs\FiscalIntegrityReport;
use Scell\Sdk\DTOs\FiscalKillSwitchStatus;
use Scell\Sdk\DTOs\FiscalRule;
use Scell\Sdk\DTOs\PaginatedResult;
use Scell\Sdk\Http\HttpClient;

class FiscalResource
{
    public function __construct(
        private readonly HttpClient $http
    ) {}

    // GET tenant/fiscal/compliance
    public function compliance(): FiscalCompliance
    {
        $response = $this->http->get('tenant/fiscal/compliance');
        return FiscalCompliance::fromArray($response['data']);
    }

    // GET tenant/fiscal/integrity
    public function integrity(array $params = []): FiscalIntegrityReport
    {
        $response = $this->http->get('tenant/fiscal/integrity', $params);
        return FiscalIntegrityReport::fromArray($response['data']);
    }

    // GET tenant/fiscal/integrity/history
    public function integrityHistory(array $params = []): PaginatedResult
    {
        $response = $this->http->get('tenant/fiscal/integrity/history', $params);
        return PaginatedResult::fromArray($response, fn(array $data) => FiscalIntegrityReport::fromArray($data));
    }

    // GET tenant/fiscal/integrity/{date}
    public function integrityForDate(string $date): FiscalIntegrityReport
    {
        $response = $this->http->get("tenant/fiscal/integrity/{$date}");
        return FiscalIntegrityReport::fromArray($response['data']);
    }

    // GET tenant/fiscal/closings
    public function closings(array $params = []): PaginatedResult
    {
        $response = $this->http->get('tenant/fiscal/closings', $params);
        return PaginatedResult::fromArray($response, fn(array $data) => FiscalClosingSummary::fromArray($data));
    }

    // POST tenant/fiscal/closings/daily
    public function performDailyClosing(array $data = []): array
    {
        return $this->http->post('tenant/fiscal/closings/daily', $data);
    }

    // GET tenant/fiscal/fec  (returns file info or binary)
    public function fecExport(array $params = []): array
    {
        return $this->http->get('tenant/fiscal/fec', $params);
    }

    // GET tenant/fiscal/attestation/{year}
    public function attestation(int $year): FiscalAttestation
    {
        $response = $this->http->get("tenant/fiscal/attestation/{$year}");
        return FiscalAttestation::fromArray($response['data']);
    }

    // GET tenant/fiscal/attestation/{year}/download
    public function attestationDownload(int $year): string
    {
        return $this->http->getRaw("tenant/fiscal/attestation/{$year}/download");
    }

    // GET tenant/fiscal/entries
    public function entries(array $params = []): PaginatedResult
    {
        $response = $this->http->get('tenant/fiscal/entries', $params);
        return PaginatedResult::fromArray($response, fn(array $data) => FiscalEntry::fromArray($data));
    }

    // GET tenant/fiscal/kill-switch/status
    public function killSwitchStatus(): FiscalKillSwitchStatus
    {
        $response = $this->http->get('tenant/fiscal/kill-switch/status');
        return FiscalKillSwitchStatus::fromArray($response['data']);
    }

    // POST tenant/fiscal/kill-switch/activate
    public function killSwitchActivate(array $data): array
    {
        return $this->http->post('tenant/fiscal/kill-switch/activate', $data);
    }

    // POST tenant/fiscal/kill-switch/deactivate
    public function killSwitchDeactivate(): array
    {
        return $this->http->post('tenant/fiscal/kill-switch/deactivate');
    }

    // GET tenant/fiscal/anchors
    public function anchors(array $params = []): PaginatedResult
    {
        $response = $this->http->get('tenant/fiscal/anchors', $params);
        return PaginatedResult::fromArray($response, fn(array $data) => FiscalAnchor::fromArray($data));
    }

    // GET tenant/fiscal/rules
    public function rules(array $params = []): PaginatedResult
    {
        $response = $this->http->get('tenant/fiscal/rules', $params);
        return PaginatedResult::fromArray($response, fn(array $data) => FiscalRule::fromArray($data));
    }

    // GET tenant/fiscal/rules/{key}
    public function ruleDetail(string $key): FiscalRule
    {
        $response = $this->http->get("tenant/fiscal/rules/{$key}");
        return FiscalRule::fromArray($response['data']);
    }

    // GET tenant/fiscal/rules/{key}/history
    public function ruleHistory(string $key, array $params = []): PaginatedResult
    {
        $response = $this->http->get("tenant/fiscal/rules/{$key}/history", $params);
        return PaginatedResult::fromArray($response, fn(array $data) => FiscalRule::fromArray($data));
    }

    // POST tenant/fiscal/rules
    public function createRule(array $data): FiscalRule
    {
        $response = $this->http->post('tenant/fiscal/rules', $data);
        return FiscalRule::fromArray($response['data']);
    }

    // PUT tenant/fiscal/rules/{id}
    public function updateRule(string $id, array $data): FiscalRule
    {
        $response = $this->http->put("tenant/fiscal/rules/{$id}", $data);
        return FiscalRule::fromArray($response['data']);
    }

    // GET tenant/fiscal/rules/export
    public function exportRules(array $params = []): array
    {
        return $this->http->get('tenant/fiscal/rules/export', $params);
    }

    // POST tenant/fiscal/rules/replay
    public function replayRules(array $data): array
    {
        return $this->http->post('tenant/fiscal/rules/replay', $data);
    }

    // GET tenant/fiscal/forensic-export
    public function forensicExport(array $params = []): array
    {
        return $this->http->get('tenant/fiscal/forensic-export', $params);
    }
}
