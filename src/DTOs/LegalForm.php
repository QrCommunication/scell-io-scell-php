<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

/**
 * Forme juridique connue d'un pays (catalogue référentiel sociétés).
 *
 * @since 2.29.0
 */
readonly class LegalForm
{
    public function __construct(
        public string $code,
        public string $label,
    ) {}

    /**
     * @param array{code: string, label: string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            code: $data['code'],
            label: $data['label'],
        );
    }
}
