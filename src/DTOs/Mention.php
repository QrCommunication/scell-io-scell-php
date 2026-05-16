<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

use InvalidArgumentException;

/**
 * Mention juridique a graver sur le PDF signe (ex: "Lu et approuve",
 * "Bon pour accord"...).
 *
 * - `signerIndex` : 0-based, doit correspondre a un signataire existant
 *   dans le payload (les mentions sont per-signer).
 * - `position` : doit inclure la `page` (int 1-indexe).
 * - `required` : si true, le signataire doit saisir la mention avant de
 *   pouvoir signer. Si false (ou si le signataire passe), `fallbackText`
 *   est utilise.
 */
readonly class Mention
{
    public function __construct(
        public string $label,
        public int $signerIndex,
        public BlockPosition $position,
        public bool $required = true,
        public ?string $fallbackText = null,
        public int $fontSize = 10,
        public string $color = '#000000',
    ) {
        if ($label === '') {
            throw new InvalidArgumentException('Mention label must not be empty.');
        }
        if ($signerIndex < 0) {
            throw new InvalidArgumentException(
                "signerIndex must be a non-negative integer (got {$signerIndex})."
            );
        }
        if ($position->page === null) {
            throw new InvalidArgumentException(
                'Mention position must include a page (int 1-indexed).'
            );
        }
        if (!is_int($position->page)) {
            throw new InvalidArgumentException(
                "Mention position.page must be an int (got '{$position->page}'). "
                . "The string 'last' is only valid for initials/date blocks."
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
            label: (string) ($data['label'] ?? ''),
            signerIndex: (int) ($data['signer_index'] ?? $data['signerIndex'] ?? 0),
            position: $blockPosition,
            required: (bool) ($data['required'] ?? true),
            fallbackText: $data['fallback_text'] ?? $data['fallbackText'] ?? null,
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
        $out = [
            'label' => $this->label,
            'required' => $this->required,
            'signer_index' => $this->signerIndex,
            'position' => $this->position->toArray(),
            'font_size' => $this->fontSize,
            'color' => $this->color,
        ];
        if ($this->fallbackText !== null) {
            $out['fallback_text'] = $this->fallbackText;
        }

        return $out;
    }
}
