<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

/**
 * Represente un resultat pagine.
 *
 * @template T
 */
readonly class PaginatedResult
{
    /**
     * @param T[] $data
     */
    public function __construct(
        public array $data,
        public int $currentPage,
        public int $lastPage,
        public int $perPage,
        public int $total,
    ) {}

    /**
     * Cree une instance a partir de la reponse API.
     *
     * @param callable(array): T $mapper
     * @return self<T>
     */
    public static function fromArray(array $response, callable $mapper): self
    {
        $data = array_map($mapper, $response['data'] ?? []);
        $meta = $response['meta'] ?? [];

        return new self(
            data: $data,
            currentPage: (int) ($meta['current_page'] ?? 1),
            lastPage: (int) ($meta['last_page'] ?? 1),
            perPage: (int) ($meta['per_page'] ?? 25),
            total: (int) ($meta['total'] ?? count($data)),
        );
    }

    /**
     * Verifie s'il y a une page suivante.
     */
    public function hasNextPage(): bool
    {
        return $this->currentPage < $this->lastPage;
    }

    /**
     * Verifie s'il y a une page precedente.
     */
    public function hasPreviousPage(): bool
    {
        return $this->currentPage > 1;
    }

    /**
     * Retourne le numero de la page suivante.
     */
    public function nextPage(): ?int
    {
        return $this->hasNextPage() ? $this->currentPage + 1 : null;
    }

    /**
     * Retourne le numero de la page precedente.
     */
    public function previousPage(): ?int
    {
        return $this->hasPreviousPage() ? $this->currentPage - 1 : null;
    }

    /**
     * Verifie si le resultat est vide.
     */
    public function isEmpty(): bool
    {
        return count($this->data) === 0;
    }

    /**
     * Retourne le nombre d'elements dans cette page.
     */
    public function count(): int
    {
        return count($this->data);
    }
}
