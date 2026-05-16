<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

use InvalidArgumentException;

/**
 * Configuration du bloc paraphe (initiales automatiques sur chaque page
 * du PDF signe).
 *
 * - `mode` : `'auto'` (genere les initiales automatiquement) ou `'custom'`
 *   (utilise `customText`).
 * - `source` : `'signer_name'` (extrait les initiales du nom du signataire)
 *   ou `'custom'` (utilise `customText`, max 8 caracteres).
 * - `pages` : `'all'` | `'except_last'` | liste explicite de pages (int[]).
 * - `position` : voir {@see BlockPosition}.
 * - `color` : hex `#RRGGBB`.
 */
readonly class InitialsBlock
{
    /**
     * @param bool            $enabled
     * @param string          $mode       `'auto'` | `'custom'`
     * @param string          $source     `'signer_name'` | `'custom'`
     * @param string|null     $customText Max 8 chars (requis si source='custom').
     * @param string|int[]    $pages      `'all'` | `'except_last'` | int[]
     * @param BlockPosition   $position
     * @param int             $fontSize
     * @param string          $color      `#RRGGBB`
     */
    public function __construct(
        public bool $enabled,
        public BlockPosition $position,
        public string $mode = 'auto',
        public string $source = 'signer_name',
        public ?string $customText = null,
        public string|array $pages = 'all',
        public int $fontSize = 10,
        public string $color = '#333333',
    ) {
        if (!in_array($mode, ['auto', 'custom'], true)) {
            throw new InvalidArgumentException(
                "Invalid mode '{$mode}'. Must be 'auto' or 'custom'."
            );
        }
        if (!in_array($source, ['signer_name', 'custom'], true)) {
            throw new InvalidArgumentException(
                "Invalid source '{$source}'. Must be 'signer_name' or 'custom'."
            );
        }
        if ($source === 'custom' && ($customText === null || $customText === '')) {
            throw new InvalidArgumentException(
                "customText is required when source='custom'."
            );
        }
        if ($customText !== null && mb_strlen($customText) > 8) {
            throw new InvalidArgumentException(
                "customText must be 8 characters or less (got " . mb_strlen($customText) . ")."
            );
        }
        if (is_string($pages) && !in_array($pages, ['all', 'except_last'], true)) {
            throw new InvalidArgumentException(
                "Invalid pages '{$pages}'. Must be 'all', 'except_last' or an array of page numbers."
            );
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
            mode: $data['mode'] ?? 'auto',
            source: $data['source'] ?? 'signer_name',
            customText: $data['custom_text'] ?? $data['customText'] ?? null,
            pages: $data['pages'] ?? 'all',
            fontSize: (int) ($data['font_size'] ?? $data['fontSize'] ?? 10),
            color: $data['color'] ?? '#333333',
        );
    }

    /**
     * Convertit en tableau pour l'API (snake_case).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $out = [
            'enabled' => $this->enabled,
            'mode' => $this->mode,
            'source' => $this->source,
            'pages' => $this->pages,
            'position' => $this->position->toArray(),
            'font_size' => $this->fontSize,
            'color' => $this->color,
        ];
        if ($this->customText !== null) {
            $out['custom_text'] = $this->customText;
        }

        return $out;
    }
}
