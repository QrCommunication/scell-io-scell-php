<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

readonly class FiscalKillSwitchStatus
{
    public function __construct(
        public bool $isActive,
        public ?array $killSwitch = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            isActive: (bool) $data['is_active'],
            killSwitch: $data['kill_switch'] ?? null,
        );
    }
}
