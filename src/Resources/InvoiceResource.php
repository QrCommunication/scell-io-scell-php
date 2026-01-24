<?php

declare(strict_types=1);

namespace Scell\Sdk\Resources;

use DateTimeInterface;
use Scell\Sdk\DTOs\Address;
use Scell\Sdk\DTOs\Invoice;
use Scell\Sdk\DTOs\InvoiceLine;
use Scell\Sdk\DTOs\PaginatedResult;
use Scell\Sdk\Enums\Direction;
use Scell\Sdk\Enums\DisputeType;
use Scell\Sdk\Enums\InvoiceStatus;
use Scell\Sdk\Enums\OutputFormat;
use Scell\Sdk\Enums\RejectionCode;
use Scell\Sdk\Http\HttpClient;

/**
 * Resource pour les factures electroniques.
 *
 * Permet de creer, lister et gerer les factures Factur-X/UBL/CII.
 */
class InvoiceResource
{
    public function __construct(
        private readonly HttpClient $http
    ) {}

    /**
     * Liste les factures avec filtrage optionnel.
     *
     * @param array{
     *     direction?: Direction|string,
     *     status?: InvoiceStatus|string,
     *     environment?: string,
     *     company_id?: string,
     *     from?: DateTimeInterface|string,
     *     to?: DateTimeInterface|string,
     *     per_page?: int,
     *     page?: int
     * } $filters
     * @return PaginatedResult<Invoice>
     */
    public function list(array $filters = []): PaginatedResult
    {
        $query = $this->normalizeFilters($filters);
        $response = $this->http->get('invoices', $query);

        return PaginatedResult::fromArray($response, fn(array $data) => Invoice::fromArray($data));
    }

    /**
     * Recupere une facture par son ID.
     */
    public function get(string $id): Invoice
    {
        $response = $this->http->get("invoices/{$id}");
        return Invoice::fromArray($response['data']);
    }

    /**
     * Cree une nouvelle facture.
     *
     * @param array{
     *     invoice_number: string,
     *     direction: Direction|string,
     *     output_format: OutputFormat|string,
     *     issue_date: DateTimeInterface|string,
     *     total_ht: float,
     *     total_tax: float,
     *     total_ttc: float,
     *     seller_siret: string,
     *     seller_name: string,
     *     seller_address: Address|array,
     *     buyer_siret: string,
     *     buyer_name: string,
     *     buyer_address: Address|array,
     *     lines: InvoiceLine[]|array[],
     *     external_id?: string,
     *     due_date?: DateTimeInterface|string,
     *     currency?: string,
     *     archive_enabled?: bool
     * } $data
     */
    public function create(array $data): Invoice
    {
        $payload = $this->normalizeCreatePayload($data);
        $response = $this->http->post('invoices', $payload);
        return Invoice::fromArray($response['data']);
    }

    /**
     * Liste les factures entrantes (fournisseurs).
     *
     * @param array{
     *     status?: InvoiceStatus|string,
     *     from?: DateTimeInterface|string,
     *     to?: DateTimeInterface|string,
     *     per_page?: int,
     *     page?: int
     * } $params
     * @return PaginatedResult<Invoice>
     */
    public function incoming(array $params = []): PaginatedResult
    {
        $query = $this->normalizeFilters($params);
        $response = $this->http->get('invoices/incoming', $query);

        return PaginatedResult::fromArray($response, fn(array $data) => Invoice::fromArray($data));
    }

    /**
     * Accepte une facture entrante.
     *
     * @param string $id ID de la facture
     * @param array{
     *     comment?: string,
     *     metadata?: array
     * } $data Donnees optionnelles
     */
    public function accept(string $id, array $data = []): Invoice
    {
        $response = $this->http->post("invoices/{$id}/accept", $data);
        return Invoice::fromArray($response['data']);
    }

    /**
     * Rejette une facture entrante.
     *
     * @param string $id ID de la facture
     * @param string $reason Motif du rejet
     * @param RejectionCode|string $reasonCode Code de rejet
     */
    public function reject(string $id, string $reason, RejectionCode|string $reasonCode): Invoice
    {
        $code = $reasonCode instanceof RejectionCode ? $reasonCode->value : $reasonCode;

        $response = $this->http->post("invoices/{$id}/reject", [
            'reason' => $reason,
            'reason_code' => $code,
        ]);

        return Invoice::fromArray($response['data']);
    }

    /**
     * Conteste une facture entrante.
     *
     * @param string $id ID de la facture
     * @param string $reason Motif de la contestation
     * @param DisputeType|string $disputeType Type de litige
     * @param float|null $expectedAmount Montant attendu (optionnel)
     */
    public function dispute(
        string $id,
        string $reason,
        DisputeType|string $disputeType,
        ?float $expectedAmount = null
    ): Invoice {
        $type = $disputeType instanceof DisputeType ? $disputeType->value : $disputeType;

        $payload = array_filter([
            'reason' => $reason,
            'dispute_type' => $type,
            'expected_amount' => $expectedAmount,
        ], fn($value) => $value !== null);

        $response = $this->http->post("invoices/{$id}/dispute", $payload);

        return Invoice::fromArray($response['data']);
    }

    /**
     * Cree une facture avec le builder fluent.
     *
     * @return InvoiceBuilder
     */
    public function builder(): InvoiceBuilder
    {
        return new InvoiceBuilder($this);
    }

    /**
     * Telecharge un fichier de facture.
     *
     * @param string $id ID de la facture
     * @param string $type Type de fichier: 'original', 'converted', 'pdf'
     * @return array{url: string, expires_at: string}
     */
    public function download(string $id, string $type = 'converted'): array
    {
        return $this->http->get("invoices/{$id}/download/{$type}");
    }

    /**
     * Recupere la piste d'audit de la facture.
     *
     * @return array{data: array[], integrity_valid: bool}
     */
    public function auditTrail(string $id): array
    {
        return $this->http->get("invoices/{$id}/audit-trail");
    }

    /**
     * Convertit une facture vers un autre format.
     *
     * @param string $invoiceId ID de la facture
     * @param OutputFormat|string $targetFormat Format cible
     * @return array{message: string, invoice_id: string, target_format: string}
     */
    public function convert(string $invoiceId, OutputFormat|string $targetFormat): array
    {
        $format = $targetFormat instanceof OutputFormat ? $targetFormat->value : $targetFormat;

        return $this->http->post('invoices/convert', [
            'invoice_id' => $invoiceId,
            'target_format' => $format,
        ]);
    }

    /**
     * Normalise les filtres de liste.
     */
    private function normalizeFilters(array $filters): array
    {
        $query = [];

        foreach ($filters as $key => $value) {
            if ($value === null) {
                continue;
            }

            if ($value instanceof Direction || $value instanceof InvoiceStatus) {
                $query[$key] = $value->value;
            } elseif ($value instanceof DateTimeInterface) {
                $query[$key] = $value->format('Y-m-d');
            } else {
                $query[$key] = $value;
            }
        }

        return $query;
    }

    /**
     * Normalise le payload de creation.
     */
    private function normalizeCreatePayload(array $data): array
    {
        $payload = [];

        // Champs simples
        $payload['invoice_number'] = $data['invoice_number'];
        $payload['direction'] = $data['direction'] instanceof Direction
            ? $data['direction']->value
            : $data['direction'];
        $payload['output_format'] = $data['output_format'] instanceof OutputFormat
            ? $data['output_format']->value
            : $data['output_format'];
        $payload['issue_date'] = $data['issue_date'] instanceof DateTimeInterface
            ? $data['issue_date']->format('Y-m-d')
            : $data['issue_date'];
        $payload['total_ht'] = $data['total_ht'];
        $payload['total_tax'] = $data['total_tax'];
        $payload['total_ttc'] = $data['total_ttc'];

        // Vendeur
        $payload['seller_siret'] = $data['seller_siret'];
        $payload['seller_name'] = $data['seller_name'];
        $payload['seller_address'] = $data['seller_address'] instanceof Address
            ? $data['seller_address']->toArray()
            : $data['seller_address'];

        // Acheteur
        $payload['buyer_siret'] = $data['buyer_siret'];
        $payload['buyer_name'] = $data['buyer_name'];
        $payload['buyer_address'] = $data['buyer_address'] instanceof Address
            ? $data['buyer_address']->toArray()
            : $data['buyer_address'];

        // Lignes
        $payload['lines'] = array_map(
            fn($line) => $line instanceof InvoiceLine ? $line->toArray() : $line,
            $data['lines']
        );

        // Champs optionnels
        if (isset($data['external_id'])) {
            $payload['external_id'] = $data['external_id'];
        }
        if (isset($data['due_date'])) {
            $payload['due_date'] = $data['due_date'] instanceof DateTimeInterface
                ? $data['due_date']->format('Y-m-d')
                : $data['due_date'];
        }
        if (isset($data['currency'])) {
            $payload['currency'] = $data['currency'];
        }
        if (isset($data['archive_enabled'])) {
            $payload['archive_enabled'] = $data['archive_enabled'];
        }

        return $payload;
    }
}

/**
 * Builder fluent pour creer des factures.
 */
class InvoiceBuilder
{
    private array $data = [];
    private array $lines = [];

    public function __construct(
        private readonly InvoiceResource $resource
    ) {}

    public function invoiceNumber(string $number): self
    {
        $this->data['invoice_number'] = $number;
        return $this;
    }

    public function externalId(string $id): self
    {
        $this->data['external_id'] = $id;
        return $this;
    }

    public function direction(Direction $direction): self
    {
        $this->data['direction'] = $direction;
        return $this;
    }

    public function outgoing(): self
    {
        return $this->direction(Direction::Outgoing);
    }

    public function incoming(): self
    {
        return $this->direction(Direction::Incoming);
    }

    public function format(OutputFormat $format): self
    {
        $this->data['output_format'] = $format;
        return $this;
    }

    public function facturX(): self
    {
        return $this->format(OutputFormat::FacturX);
    }

    public function ubl(): self
    {
        return $this->format(OutputFormat::UBL);
    }

    public function cii(): self
    {
        return $this->format(OutputFormat::CII);
    }

    public function issueDate(DateTimeInterface|string $date): self
    {
        $this->data['issue_date'] = $date;
        return $this;
    }

    public function dueDate(DateTimeInterface|string $date): self
    {
        $this->data['due_date'] = $date;
        return $this;
    }

    public function currency(string $currency): self
    {
        $this->data['currency'] = $currency;
        return $this;
    }

    public function seller(string $siret, string $name, Address|array $address): self
    {
        $this->data['seller_siret'] = $siret;
        $this->data['seller_name'] = $name;
        $this->data['seller_address'] = $address;
        return $this;
    }

    public function buyer(string $siret, string $name, Address|array $address): self
    {
        $this->data['buyer_siret'] = $siret;
        $this->data['buyer_name'] = $name;
        $this->data['buyer_address'] = $address;
        return $this;
    }

    public function addLine(string $description, float $quantity, float $unitPrice, float $taxRate): self
    {
        $this->lines[] = InvoiceLine::create($description, $quantity, $unitPrice, $taxRate);
        return $this;
    }

    public function addLineDto(InvoiceLine $line): self
    {
        $this->lines[] = $line;
        return $this;
    }

    public function archiveEnabled(bool $enabled = true): self
    {
        $this->data['archive_enabled'] = $enabled;
        return $this;
    }

    /**
     * Calcule automatiquement les totaux et cree la facture.
     */
    public function create(): Invoice
    {
        // Calculer les totaux si non fournis
        if (!isset($this->data['total_ht'])) {
            $totalHt = array_sum(array_map(fn(InvoiceLine $l) => $l->totalHt, $this->lines));
            $totalTax = array_sum(array_map(fn(InvoiceLine $l) => $l->totalTax, $this->lines));
            $this->data['total_ht'] = round($totalHt, 2);
            $this->data['total_tax'] = round($totalTax, 2);
            $this->data['total_ttc'] = round($totalHt + $totalTax, 2);
        }

        $this->data['lines'] = $this->lines;

        return $this->resource->create($this->data);
    }
}
