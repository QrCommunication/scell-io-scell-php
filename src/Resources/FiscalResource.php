<?php

declare(strict_types=1);

namespace Scell\Sdk\Resources;

use DateTimeInterface;
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

    /**
     * @return PaginatedResult<FiscalIntegrityReport>
     */
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

    /**
     * @return PaginatedResult<FiscalClosingSummary>
     */
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

    /**
     * Genere et retourne l'export FEC ZIP consolide couvrant TOUS les sub-tenants
     * du tenant courant + le tenant lui-meme (cross sub-tenant export).
     *
     * GET `/v1/tenant/fiscal/fec/all` (P0.1 — disponible depuis 2026-05-27).
     *
     * En mode JSON par defaut, retourne les metadata + chemin du ZIP genere
     * (le ZIP est materialise cote serveur, telechargeable separement). Pour
     * forcer le telechargement direct du binaire ZIP, passer `download = true`
     * ce qui appelle l'API avec `?download=1` et retourne le binaire brut.
     *
     * Le format `pipe` (defaut) suit la norme FEC francaise (CGI BOI-CF-IOR-60-40-10).
     * Le format `tab` produit un FEC tabulaire alternatif.
     *
     * @param string|DateTimeInterface $startDate Date de debut de periode (YYYY-MM-DD)
     * @param string|DateTimeInterface $endDate   Date de fin (incluse, YYYY-MM-DD)
     * @param 'pipe'|'tab' $format Separateur FEC (defaut `pipe`)
     * @param bool $download Si true, retourne le binaire ZIP a la place du JSON
     *
     * @return array<string, mixed>|string
     *   - Array JSON `{success, message, data: {period, format, file_path}}` si `$download = false`
     *   - String binaire (contenu du ZIP) si `$download = true`
     *
     * @throws \Scell\Sdk\Exceptions\ValidationException 422 si dates invalides ou period vide
     *
     * @example
     * ```php
     * // Metadata seulement (file_path serveur)
     * $meta = $client->fiscal()->exportFecAll('2026-01-01', '2026-12-31');
     * echo $meta['data']['file_path']; // /tmp/fec_all_2026.zip
     *
     * // Telechargement direct du ZIP
     * $zipBinary = $client->fiscal()->exportFecAll(
     *     new \DateTimeImmutable('2026-01-01'),
     *     new \DateTimeImmutable('2026-12-31'),
     *     format: 'pipe',
     *     download: true,
     * );
     * file_put_contents('fec-all-2026.zip', $zipBinary);
     * ```
     *
     * @since 2.24.0
     */
    public function exportFecAll(
        string|DateTimeInterface $startDate,
        string|DateTimeInterface $endDate,
        string $format = 'pipe',
        bool $download = false,
    ): array|string {
        $query = [
            'start_date' => $startDate instanceof DateTimeInterface
                ? $startDate->format('Y-m-d')
                : $startDate,
            'end_date' => $endDate instanceof DateTimeInterface
                ? $endDate->format('Y-m-d')
                : $endDate,
            'format' => $format,
        ];

        if ($download) {
            $query['download'] = 1;
            return $this->http->getRaw('tenant/fiscal/fec/all', $query);
        }

        return $this->http->get('tenant/fiscal/fec/all', $query);
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

    /**
     * Telecharge l'auto-attestation ISCA NOMINATIVE (PDF binaire) pour le
     * tenant authentifie ou pour un sub_tenant specifique.
     *
     * Le PDF inclut :
     *  - Identite du logiciel (Scell.io + version)
     *  - Identite de l'editeur (QR Communication SAS)
     *  - **Identite nominative du beneficiaire** (tenant ou sub_tenant : nom,
     *    SIRET, TVA, adresse, contact, statut KYB/KYC)
     *  - Declaration de conformite ISCA (4 piliers : I, S, C, A)
     *  - Empreinte d'integrite SHA-256 du document
     *
     * Le hash couvre l'identite du beneficiaire — 2 attestations pour 2
     * beneficiaires distincts auront 2 hashes differents (preuve cryptographique
     * de la non-transferabilite).
     *
     * @param string|null $subTenantId UUID optionnel du sub_tenant. Si fourni,
     *   attestation emise pour ce sub_tenant (404 si introuvable ou n'appartient
     *   pas au tenant). Sinon, attestation emise pour le tenant authentifie.
     * @return string Contenu binaire du PDF (a ecrire avec file_put_contents).
     *
     * @example
     *   // Attestation pour le tenant
     *   $pdf = $scell->fiscal->iscaSelfAttestationDownload();
     *   file_put_contents('attestation-tenant.pdf', $pdf);
     *
     *   // Attestation pour un sub_tenant
     *   $pdf = $scell->fiscal->iscaSelfAttestationDownload('019d5ea8-...');
     *   file_put_contents('attestation-sub.pdf', $pdf);
     */
    public function iscaSelfAttestationDownload(?string $subTenantId = null): string
    {
        $path = $subTenantId
            ? "tenant/fiscal/isca/self-attestation/{$subTenantId}/download"
            : 'tenant/fiscal/isca/self-attestation/download';
        return $this->http->getRaw($path);
    }

    /**
     * Telecharge le registre des mesures ISCA (PDF). Document non-nominatif
     * decrivant les mesures techniques de conformite implementees par Scell.io.
     */
    public function iscaMeasuresRegisterDownload(): string
    {
        return $this->http->getRaw('tenant/fiscal/isca/measures-register/download');
    }

    /**
     * Telecharge le dossier technique ISCA (PDF). Document non-nominatif
     * decrivant l'architecture technique conforme aux exigences NF Z 42-025.
     */
    public function iscaTechnicalDossierDownload(): string
    {
        return $this->http->getRaw('tenant/fiscal/isca/technical-dossier/download');
    }

    /**
     * @return PaginatedResult<FiscalEntry>
     */
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

    /**
     * Activate the fiscal kill-switch (emergency halt).
     *
     * Requires the `fiscal:admin` scope (fail-closed). The `$data` array must
     * carry a `reason` of **>= 20 characters**. In production, the first call
     * returns 403 `OOB_CONFIRMATION_REQUIRED` and emails a `confirmation_token`
     * to the tenant contact — re-call with that `confirmation_token` to apply.
     *
     * @param array{reason: string, confirmation_token?: string} $data
     */
    // POST tenant/fiscal/kill-switch/activate
    public function killSwitchActivate(array $data): array
    {
        return $this->http->post('tenant/fiscal/kill-switch/activate', $data);
    }

    /**
     * Deactivate the fiscal kill-switch.
     *
     * Same step-up contract as activation: `$data['reason']` must be **>= 20
     * characters**, and in production an out-of-band `confirmation_token` is
     * required (first call returns 403 `OOB_CONFIRMATION_REQUIRED` and emails
     * the token).
     *
     * @param array{reason: string, confirmation_token?: string} $data
     */
    // POST tenant/fiscal/kill-switch/deactivate
    public function killSwitchDeactivate(array $data): array
    {
        return $this->http->post('tenant/fiscal/kill-switch/deactivate', $data);
    }

    /**
     * @return PaginatedResult<FiscalAnchor>
     */
    public function anchors(array $params = []): PaginatedResult
    {
        $response = $this->http->get('tenant/fiscal/anchors', $params);
        return PaginatedResult::fromArray($response, fn(array $data) => FiscalAnchor::fromArray($data));
    }

    /**
     * @return PaginatedResult<FiscalRule>
     */
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

    /**
     * @return PaginatedResult<FiscalRule>
     */
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

    /**
     * Telecharge le registre des mesures ISCA en PDF.
     */
    public function downloadMeasuresRegister(): string
    {
        return $this->http->getRaw('tenant/fiscal/isca/measures-register/download');
    }

    /**
     * Telecharge le dossier technique ISCA en PDF.
     */
    public function downloadTechnicalDossier(): string
    {
        return $this->http->getRaw('tenant/fiscal/isca/technical-dossier/download');
    }

    /**
     * Telecharge l'auto-attestation ISCA en PDF.
     */
    public function downloadSelfAttestation(): string
    {
        return $this->http->getRaw('tenant/fiscal/isca/self-attestation/download');
    }
}
