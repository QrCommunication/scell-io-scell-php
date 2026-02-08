<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

readonly class FiscalAuditLog
{
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $action,
        public string $endpoint,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
        public ?array $requestParams = null,
        public ?int $responseStatus = null,
        public ?string $createdAt = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            tenantId: $data['tenant_id'],
            action: $data['action'],
            endpoint: $data['endpoint'],
            ipAddress: $data['ip_address'] ?? null,
            userAgent: $data['user_agent'] ?? null,
            requestParams: $data['request_params'] ?? null,
            responseStatus: isset($data['response_status']) ? (int) $data['response_status'] : null,
            createdAt: $data['created_at'] ?? null,
        );
    }
}
