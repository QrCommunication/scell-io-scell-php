<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

use InvalidArgumentException;

/**
 * Configuration du bloc paraphe (initiales) appose automatiquement sur le PDF.
 *
 * Deux formats acceptés :
 *
 *  1. Nouveau (recommandé, depuis v2.15.0) — `positions[]` avec une entrée
 *     par page. Chaque entrée peut définir ses propres `x`, `y`, `font_size`,
 *     `color`, `bold`.
 *
 *  2. Legacy (rétrocompat) — `position` (UNE position commune) + `pages`
 *     (`'all'`, `'except_last'` ou liste d'entiers).
 *
 * Si les DEUX formats sont fournis, `positions[]` prévaut (le format legacy
 * est ignoré).
 *
 * Mode :
 *  - `'auto'` : initiales générées automatiquement depuis le 1er signataire.
 *  - `'custom'` : utilise `customText` (max 8 caracteres).
 *
 * @example Nouveau format positions[]
 * ```php
 * use Scell\Sdk\DTOs\InitialsBlock;
 * use Scell\Sdk\DTOs\InitialsPosition;
 *
 * $block = new InitialsBlock(
 *     enabled: true,
 *     positions: [
 *         new InitialsPosition(page: 1, x: 90, y: 90),
 *         new InitialsPosition(page: 2, x: 88, y: 92),
 *         new InitialsPosition(page: 3, x: 85, y: 90),
 *     ],
 *     fontSize: 10,
 *     color: '#000000',
 * );
 * ```
 *
 * @example Format legacy (toujours supporté)
 * ```php
 * use Scell\Sdk\DTOs\InitialsBlock;
 * use Scell\Sdk\DTOs\BlockPosition;
 *
 * $block = new InitialsBlock(
 *     enabled: true,
 *     position: new BlockPosition(x: 90, y: 90),
 *     pages: 'except_last',
 * );
 * ```
 */
readonly class InitialsBlock
{
    /**
     * @param bool $enabled
     * @param BlockPosition|null $position Legacy : UNE position commune. Ignoré si `positions` est fourni.
     * @param string $mode `'auto'` | `'custom'`
     * @param string $source `'signer_name'` | `'custom'`
     * @param string|null $customText Max 8 chars (requis si source='custom').
     * @param string|int[] $pages Legacy : `'all'` | `'except_last'` | int[]. Ignoré si `positions` est fourni.
     * @param int $fontSize Defaut applique aux positions sans override.
     * @param string $color Defaut applique aux positions sans override.
     * @param bool $bold Defaut applique aux positions sans override.
     * @param list<InitialsPosition>|null $positions Nouveau format (1 entrée par page).
     */
    public function __construct(
        public bool $enabled,
        public ?BlockPosition $position = null,
        public string $mode = 'auto',
        public string $source = 'signer_name',
        public ?string $customText = null,
        public string|array $pages = 'all',
        public int $fontSize = 10,
        public string $color = '#333333',
        public bool $bold = false,
        public ?array $positions = null,
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
                'customText must be 8 characters or less (got ' . mb_strlen($customText) . ').'
            );
        }
        if (is_string($pages) && !in_array($pages, ['all', 'except_last'], true)) {
            throw new InvalidArgumentException(
                "Invalid pages '{$pages}'. Must be 'all', 'except_last' or an array of page numbers."
            );
        }
        if ($positions !== null) {
            if ($positions === []) {
                throw new InvalidArgumentException(
                    'positions must contain at least one InitialsPosition (use null to disable, or use legacy position+pages).'
                );
            }
            foreach ($positions as $i => $position) {
                if (!$position instanceof InitialsPosition) {
                    throw new InvalidArgumentException(
                        "positions[{$i}] must be an instance of " . InitialsPosition::class . '.'
                    );
                }
            }
        }
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            throw new InvalidArgumentException(
                "Invalid color '{$color}'. Must be a hex code (#RRGGBB)."
            );
        }
    }

    /**
     * Constructeur convenance : positions multiples sans BlockPosition legacy.
     *
     * @param list<InitialsPosition> $positions
     */
    public static function withPositions(
        array $positions,
        string $mode = 'auto',
        string $source = 'signer_name',
        ?string $customText = null,
        int $fontSize = 10,
        string $color = '#333333',
        bool $bold = false,
    ): self {
        return new self(
            enabled: true,
            position: null,
            mode: $mode,
            source: $source,
            customText: $customText,
            pages: 'all',
            fontSize: $fontSize,
            color: $color,
            bold: $bold,
            positions: $positions,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        // Resoudre position legacy (peut etre null en mode positions[]).
        $position = null;
        if (isset($data['position']) && $data['position'] !== null && $data['position'] !== []) {
            $position = $data['position'] instanceof BlockPosition
                ? $data['position']
                : BlockPosition::fromArray((array) $data['position']);
        }

        // Resoudre positions[] nouveau format.
        $positions = null;
        if (isset($data['positions']) && is_array($data['positions']) && $data['positions'] !== []) {
            $positions = array_map(
                static fn (mixed $p): InitialsPosition => $p instanceof InitialsPosition
                    ? $p
                    : InitialsPosition::fromArray((array) $p),
                $data['positions']
            );
        }

        return new self(
            enabled: (bool) ($data['enabled'] ?? true),
            position: $position,
            mode: $data['mode'] ?? 'auto',
            source: $data['source'] ?? 'signer_name',
            customText: $data['custom_text'] ?? $data['customText'] ?? null,
            pages: $data['pages'] ?? 'all',
            fontSize: (int) ($data['font_size'] ?? $data['fontSize'] ?? 10),
            color: $data['color'] ?? '#333333',
            bold: (bool) ($data['bold'] ?? false),
            positions: $positions,
        );
    }

    /**
     * Convertit en tableau snake_case pour l'API.
     *
     * Si `positions` est fourni, le payload utilise le nouveau format ; sinon
     * le legacy `position + pages` est emis (retrocompat des clients existants).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $out = [
            'enabled' => $this->enabled,
            'mode' => $this->mode,
            'source' => $this->source,
            'font_size' => $this->fontSize,
            'color' => $this->color,
            'bold' => $this->bold,
        ];
        if ($this->customText !== null) {
            $out['custom_text'] = $this->customText;
        }

        if ($this->positions !== null && $this->positions !== []) {
            // Nouveau format : positions[] explicite. On omet position+pages
            // pour ne pas creer d'ambiguite (positions[] gagne cote backend).
            $out['positions'] = array_map(
                static fn (InitialsPosition $p): array => $p->toArray(),
                $this->positions
            );

            return $out;
        }

        // Legacy : position + pages.
        $out['pages'] = $this->pages;
        if ($this->position !== null) {
            $out['position'] = $this->position->toArray();
        }

        return $out;
    }
}
