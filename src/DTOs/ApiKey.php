<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

readonly class ApiKey
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $lastUsedAt = null,
        public ?string $createdAt = null,
        public ?string $key = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            name: $data['name'],
            lastUsedAt: $data['last_used_at'] ?? null,
            createdAt: $data['created_at'] ?? null,
            key: $data['key'] ?? $data['plain_text_key'] ?? null,
        );
    }
}
