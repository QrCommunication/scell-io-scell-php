<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

/**
 * Represente une adresse.
 */
readonly class Address
{
    public function __construct(
        public string $line1,
        public string $postalCode,
        public string $city,
        public ?string $line2 = null,
        public string $country = 'FR',
    ) {}

    /**
     * Cree une instance a partir d'un tableau.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            line1: $data['line1'] ?? $data['address_line1'] ?? '',
            postalCode: $data['postal_code'] ?? $data['postalCode'] ?? '',
            city: $data['city'] ?? '',
            line2: $data['line2'] ?? $data['address_line2'] ?? null,
            country: $data['country'] ?? 'FR',
        );
    }

    /**
     * Convertit en tableau pour l'API.
     */
    public function toArray(): array
    {
        return array_filter([
            'line1' => $this->line1,
            'line2' => $this->line2,
            'postal_code' => $this->postalCode,
            'city' => $this->city,
            'country' => $this->country,
        ], fn($value) => $value !== null);
    }

    /**
     * Retourne l'adresse formatee sur plusieurs lignes.
     */
    public function formatted(): string
    {
        $lines = [$this->line1];
        if ($this->line2) {
            $lines[] = $this->line2;
        }
        $lines[] = "{$this->postalCode} {$this->city}";
        if ($this->country !== 'FR') {
            $lines[] = $this->country;
        }
        return implode("\n", $lines);
    }
}
