<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

use DateTimeImmutable;
use Scell\Sdk\Enums\Environment;
use Scell\Sdk\Enums\SignatureStatus;

/**
 * Represente une demande de signature electronique.
 */
readonly class Signature
{
    /**
     * @param Signer[] $signers
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $documentName,
        public int $documentSize,
        public array $signers,
        public SignatureStatus $status,
        public Environment $environment,
        public ?string $externalId = null,
        public ?string $description = null,
        public ?string $statusMessage = null,
        public bool $archiveEnabled = false,
        public ?float $amountCharged = null,
        public ?DateTimeImmutable $expiresAt = null,
        public ?DateTimeImmutable $createdAt = null,
        public ?DateTimeImmutable $completedAt = null,
    ) {}

    /**
     * Cree une instance a partir de la reponse API.
     */
    public static function fromArray(array $data): self
    {
        $signers = array_map(
            fn(array $signer) => Signer::fromArray($signer),
            $data['signers'] ?? []
        );

        return new self(
            id: $data['id'],
            title: $data['title'],
            documentName: $data['document_name'],
            documentSize: (int) $data['document_size'],
            signers: $signers,
            status: SignatureStatus::from($data['status']),
            environment: Environment::from($data['environment']),
            externalId: $data['external_id'] ?? null,
            description: $data['description'] ?? null,
            statusMessage: $data['status_message'] ?? null,
            archiveEnabled: $data['archive_enabled'] ?? false,
            amountCharged: isset($data['amount_charged']) ? (float) $data['amount_charged'] : null,
            expiresAt: isset($data['expires_at']) ? new DateTimeImmutable($data['expires_at']) : null,
            createdAt: isset($data['created_at']) ? new DateTimeImmutable($data['created_at']) : null,
            completedAt: isset($data['completed_at']) ? new DateTimeImmutable($data['completed_at']) : null,
        );
    }

    /**
     * Verifie si la signature est en mode sandbox.
     */
    public function isSandbox(): bool
    {
        return $this->environment->isSandbox();
    }

    /**
     * Verifie si la signature est terminee.
     */
    public function isCompleted(): bool
    {
        return $this->status === SignatureStatus::Completed;
    }

    /**
     * Verifie si la signature est expiree.
     */
    public function isExpired(): bool
    {
        if ($this->status === SignatureStatus::Expired) {
            return true;
        }

        if ($this->expiresAt !== null) {
            return $this->expiresAt < new DateTimeImmutable();
        }

        return false;
    }

    /**
     * Verifie si un rappel peut etre envoye.
     */
    public function canRemind(): bool
    {
        return $this->status->canRemind();
    }

    /**
     * Retourne le nombre de signataires en attente.
     */
    public function pendingSignersCount(): int
    {
        return count(array_filter($this->signers, fn(Signer $s) => $s->isPending()));
    }

    /**
     * Retourne le nombre de signataires ayant signe.
     */
    public function signedCount(): int
    {
        return count(array_filter($this->signers, fn(Signer $s) => $s->hasSigned()));
    }

    /**
     * Retourne la progression (0-100).
     */
    public function progress(): int
    {
        $total = count($this->signers);
        if ($total === 0) {
            return 0;
        }

        return (int) round(($this->signedCount() / $total) * 100);
    }
}
