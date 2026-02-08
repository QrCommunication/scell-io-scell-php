<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

readonly class FiscalIntegrityReport
{
    public function __construct(
        public bool $isValid,
        public int $entriesChecked,
        public int $brokenLinks,
        public array $details = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            isValid: (bool) ($data['is_valid'] ?? $data['valid'] ?? true),
            entriesChecked: (int) ($data['entries_checked'] ?? 0),
            brokenLinks: (int) ($data['broken_links'] ?? 0),
            details: $data['details'] ?? [],
        );
    }
}
