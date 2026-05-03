<?php

declare(strict_types=1);

namespace Scell\Sdk\Resources;

use Scell\Sdk\DTOs\InvoiceTemplate;
use Scell\Sdk\DTOs\PaginatedResult;
use Scell\Sdk\Http\HttpClient;

/**
 * CRUD des templates de personnalisation factures / avoirs.
 *
 * @example
 * ```php
 * $tpl = $client->invoiceTemplates()->create([
 *     'scope' => 'tenant',
 *     'name' => 'Brand Q4 2026',
 *     'logo_url' => 'https://cdn.client.com/logo.svg',
 *     'primary_color' => '#0F172A',
 *     'is_default' => true,
 *     'is_available_to_subtenants' => true,
 * ]);
 * ```
 */
class InvoiceTemplateResource
{
    public function __construct(
        private readonly HttpClient $http,
    ) {}

    /**
     * Liste les templates accessibles au contexte courant.
     *
     * @return PaginatedResult<InvoiceTemplate>
     */
    public function list(array $filters = []): PaginatedResult
    {
        $response = $this->http->get('invoice-templates', $filters);
        return PaginatedResult::fromArray($response, fn (array $row) => InvoiceTemplate::fromArray($row));
    }

    public function get(string $id): InvoiceTemplate
    {
        $response = $this->http->get("invoice-templates/{$id}");
        return InvoiceTemplate::fromArray($response['data']);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): InvoiceTemplate
    {
        $response = $this->http->post('invoice-templates', $data);
        return InvoiceTemplate::fromArray($response['data']);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(string $id, array $data): InvoiceTemplate
    {
        $response = $this->http->patch("invoice-templates/{$id}", $data);
        return InvoiceTemplate::fromArray($response['data']);
    }

    public function delete(string $id): void
    {
        $this->http->delete("invoice-templates/{$id}");
    }

    /**
     * Marque un template comme default pour son scope.
     */
    public function markDefault(string $id): InvoiceTemplate
    {
        $response = $this->http->put("invoice-templates/{$id}/default");
        return InvoiceTemplate::fromArray($response['data']);
    }
}
