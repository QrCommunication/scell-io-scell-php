<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

use InvalidArgumentException;

/**
 * Position d'un bloc (paraphe / mention / date) sur le PDF.
 *
 * Unite par defaut : `'percent'` (0-100 relatif a la page).
 * Pour des coordonnees pixel absolues @72dpi : passer `unit: 'pixel'`.
 *
 * - Pour un bloc paraphe / date : la `page` est soit un entier 1-indexe,
 *   soit la chaine `'last'` (calculee cote backend).
 * - Pour une mention : la `page` est OBLIGATOIRE (entier 1-indexe).
 * - `w` / `h` sont optionnels (taille du bloc) : si absents, le backend
 *   utilise une taille calculee depuis `font_size`.
 */
readonly class BlockPosition
{
    public function __construct(
        public float $x,
        public float $y,
        public int|string|null $page = null,
        public ?float $w = null,
        public ?float $h = null,
        public string $unit = 'percent',
    ) {
        if (!in_array($unit, ['percent', 'pixel'], true)) {
            throw new InvalidArgumentException(
                "Invalid unit '{$unit}'. Must be 'percent' or 'pixel'."
            );
        }
        if (is_string($page) && $page !== 'last') {
            throw new InvalidArgumentException(
                "Invalid page '{$page}'. Must be a positive integer or the string 'last'."
            );
        }
        if (is_int($page) && $page < 1) {
            throw new InvalidArgumentException(
                "Invalid page {$page}. Must be a positive integer (1-indexed)."
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
        return new self(
            x: (float) ($data['x'] ?? 0.0),
            y: (float) ($data['y'] ?? 0.0),
            page: $data['page'] ?? null,
            w: isset($data['w']) ? (float) $data['w'] : null,
            h: isset($data['h']) ? (float) $data['h'] : null,
            unit: $data['unit'] ?? 'percent',
        );
    }

    /**
     * Convertit en tableau pour l'API. Les champs `null` sont omis.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $out = [
            'x' => $this->x,
            'y' => $this->y,
            'unit' => $this->unit,
        ];
        if ($this->page !== null) {
            $out['page'] = $this->page;
        }
        if ($this->w !== null) {
            $out['w'] = $this->w;
        }
        if ($this->h !== null) {
            $out['h'] = $this->h;
        }

        return $out;
    }
}
