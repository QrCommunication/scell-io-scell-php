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
 * - `documentIndex` (v2.16.0) : permet de cibler un document specifique
 *   dans un bundle multi-PDF (`attachments[]` du payload de creation).
 *   `0` = document principal (`document`/`document_name`), `1..N` =
 *   attachments dans l'ordre du tableau. Defaut `null` (= document
 *   principal cote backend). Plage validee : 0..10 (max 10 PJ).
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
        public ?int $documentIndex = null,
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
        if ($documentIndex !== null && ($documentIndex < 0 || $documentIndex > 10)) {
            throw new InvalidArgumentException(
                "Invalid documentIndex {$documentIndex}. Must be between 0 and 10 (0 = main document, 1..N = attachments)."
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
            documentIndex: isset($data['document_index']) ? (int) $data['document_index'] : null,
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
        if ($this->documentIndex !== null) {
            $out['document_index'] = $this->documentIndex;
        }

        return $out;
    }
}
