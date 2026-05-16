<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

use InvalidArgumentException;

/**
 * Configuration du bloc date du jour grave sur le PDF signe.
 *
 * - `format` : format PHP `date()` (ex: `'d/m/Y'`, `'Y-m-d'`).
 * - `timezone` : identifiant IANA (ex: `'Europe/Paris'`).
 * - `position` : voir {@see BlockPosition}. La `page` peut etre un int
 *   1-indexe ou la chaine `'last'`.
 * - `color` : hex `#RRGGBB`.
 */
readonly class DateBlock
{
    public function __construct(
        public bool $enabled,
        public BlockPosition $position,
        public string $format = 'd/m/Y',
        public string $timezone = 'Europe/Paris',
        public int $fontSize = 10,
        public string $color = '#000000',
    ) {
        if ($format === '') {
            throw new InvalidArgumentException('DateBlock format must not be empty.');
        }
        if ($timezone === '') {
            throw new InvalidArgumentException('DateBlock timezone must not be empty.');
        }
    }

    /**
     * Cree une instance a partir d'un tableau.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $position = $data['position'] ?? [];
        if ($position instanceof BlockPosition) {
            $blockPosition = $position;
        } else {
            $blockPosition = BlockPosition::fromArray((array) $position);
        }

        return new self(
            enabled: (bool) ($data['enabled'] ?? true),
            position: $blockPosition,
            format: $data['format'] ?? 'd/m/Y',
            timezone: $data['timezone'] ?? 'Europe/Paris',
            fontSize: (int) ($data['font_size'] ?? $data['fontSize'] ?? 10),
            color: $data['color'] ?? '#000000',
        );
    }

    /**
     * Convertit en tableau pour l'API (snake_case).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'format' => $this->format,
            'timezone' => $this->timezone,
            'position' => $this->position->toArray(),
            'font_size' => $this->fontSize,
            'color' => $this->color,
        ];
    }
}
