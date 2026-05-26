<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

use InvalidArgumentException;

/**
 * Position d'un paraphe (initiales) sur UNE page precise d'un PDF.
 *
 * Introduit en v2.15.0. Remplace l'ancien couple `InitialsBlock::position` +
 * `InitialsBlock::pages` (qui forcait la MEME position sur toutes les pages
 * choisies) par une liste de positions individuelles par page.
 *
 * Champs obligatoires : `page`, `x`, `y`.
 *
 * Champs optionnels per-position :
 *  - `unit` : `'percent'` (defaut) ou `'pixel'`.
 *  - `pageWidthPx` / `pageHeightPx` : utilises pour la conversion pixel->mm
 *    quand `unit='pixel'`. Si absents : detection auto via parser PDF cote
 *    backend, fallback A4 (595 x 842).
 *  - `fontSize`, `color`, `bold` : override des valeurs au niveau du bloc.
 *  - `documentIndex` (v2.16.0) : cible un document specifique dans un bundle
 *    multi-PDF (`attachments[]` du payload de creation). `0` = document
 *    principal, `1..N` = attachments dans l'ordre. Defaut `null` = principal.
 *
 * @example
 * ```php
 * use Scell\Sdk\DTOs\InitialsPosition;
 *
 * $p1 = new InitialsPosition(page: 1, x: 90, y: 90);
 * $p2 = new InitialsPosition(page: 2, x: 88, y: 92, fontSize: 12);
 * $p3 = new InitialsPosition(page: 3, x: 85, y: 90, color: '#aa0000');
 *
 * // Cible le 2e document du bundle (attachments[0])
 * $p4 = new InitialsPosition(page: 1, x: 90, y: 90, documentIndex: 1);
 * ```
 */
readonly class InitialsPosition
{
    public function __construct(
        public int $page,
        public float $x,
        public float $y,
        public string $unit = 'percent',
        public ?int $pageWidthPx = null,
        public ?int $pageHeightPx = null,
        public ?int $fontSize = null,
        public ?string $color = null,
        public ?bool $bold = null,
        public ?int $documentIndex = null,
    ) {
        if ($page < 1 || $page > 500) {
            throw new InvalidArgumentException(
                "Invalid page {$page}. Must be between 1 and 500 (1-indexed)."
            );
        }
        if (!in_array($unit, ['percent', 'pixel'], true)) {
            throw new InvalidArgumentException(
                "Invalid unit '{$unit}'. Must be 'percent' or 'pixel'."
            );
        }
        if ($fontSize !== null && ($fontSize < 6 || $fontSize > 20)) {
            throw new InvalidArgumentException(
                "Invalid fontSize {$fontSize}. Must be between 6 and 20."
            );
        }
        if ($color !== null && !preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            throw new InvalidArgumentException(
                "Invalid color '{$color}'. Must be a hex code (#RRGGBB)."
            );
        }
        if ($documentIndex !== null && ($documentIndex < 0 || $documentIndex > 10)) {
            throw new InvalidArgumentException(
                "Invalid documentIndex {$documentIndex}. Must be between 0 and 10 (0 = main document, 1..N = attachments)."
            );
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            page: (int) ($data['page'] ?? 1),
            x: (float) ($data['x'] ?? 0.0),
            y: (float) ($data['y'] ?? 0.0),
            unit: $data['unit'] ?? 'percent',
            pageWidthPx: isset($data['page_width_px']) ? (int) $data['page_width_px'] : null,
            pageHeightPx: isset($data['page_height_px']) ? (int) $data['page_height_px'] : null,
            fontSize: isset($data['font_size']) ? (int) $data['font_size'] : null,
            color: $data['color'] ?? null,
            bold: isset($data['bold']) ? (bool) $data['bold'] : null,
            documentIndex: isset($data['document_index']) ? (int) $data['document_index'] : null,
        );
    }

    /**
     * Convertit en tableau snake_case pour l'API. Les champs `null` sont omis.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $out = [
            'page' => $this->page,
            'x' => $this->x,
            'y' => $this->y,
            'unit' => $this->unit,
        ];
        if ($this->pageWidthPx !== null) {
            $out['page_width_px'] = $this->pageWidthPx;
        }
        if ($this->pageHeightPx !== null) {
            $out['page_height_px'] = $this->pageHeightPx;
        }
        if ($this->fontSize !== null) {
            $out['font_size'] = $this->fontSize;
        }
        if ($this->color !== null) {
            $out['color'] = $this->color;
        }
        if ($this->bold !== null) {
            $out['bold'] = $this->bold;
        }
        if ($this->documentIndex !== null) {
            $out['document_index'] = $this->documentIndex;
        }

        return $out;
    }
}
