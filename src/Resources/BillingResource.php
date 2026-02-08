<?php

declare(strict_types=1);

namespace Scell\Sdk\Resources;

use Scell\Sdk\DTOs\BillingInvoice;
use Scell\Sdk\DTOs\BillingTransaction;
use Scell\Sdk\DTOs\BillingUsage;
use Scell\Sdk\DTOs\PaginatedResult;
use Scell\Sdk\Http\HttpClient;

class BillingResource
{
    public function __construct(
        private readonly HttpClient $http
    ) {}

    public function invoices(array $params = []): PaginatedResult
    {
        $response = $this->http->get('tenant/billing/invoices', $params);
        return PaginatedResult::fromArray($response, fn(array $data) => BillingInvoice::fromArray($data));
    }

    public function showInvoice(string $invoiceId): BillingInvoice
    {
        $response = $this->http->get("tenant/billing/invoices/{$invoiceId}");
        return BillingInvoice::fromArray($response['data']);
    }

    public function downloadInvoice(string $invoiceId): string
    {
        return $this->http->getRaw("tenant/billing/invoices/{$invoiceId}/download");
    }

    public function usage(array $params = []): BillingUsage
    {
        $response = $this->http->get('tenant/billing/usage', $params);
        return BillingUsage::fromArray($response['data']);
    }

    public function topUp(array $data): array
    {
        return $this->http->post('tenant/billing/top-up', $data);
    }

    public function confirmTopUp(array $data): array
    {
        return $this->http->post('tenant/billing/top-up/confirm', $data);
    }

    public function transactions(array $params = []): PaginatedResult
    {
        $response = $this->http->get('tenant/billing/transactions', $params);
        return PaginatedResult::fromArray($response, fn(array $data) => BillingTransaction::fromArray($data));
    }
}
