<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

/**
 * Represente le scellement d'un devis signe : signature PAdES du PDF
 * + ancrage du hash dans la blockchain Bitcoin via OpenTimestamps (OTS).
 *
 * Le PDF scelle est signe au format PAdES, son empreinte SHA-256
 * (signedPdfSha256) est soumise aux calendars OpenTimestamps puis ancree
 * dans un bloc Bitcoin. Le receipt .ots (otsProofBase64) permet de verifier
 * l'ancrage de maniere independante.
 */
readonly class QuoteSealing
{
    public function __construct(
        public bool $isSealed,
        /** Date de signature PAdES (ISO 8601). */
        public ?string $padesSignedAt = null,
        /** Empreinte SHA-256 (hex) du PDF scelle = hash ancre dans Bitcoin. */
        public ?string $signedPdfSha256 = null,
        /** Statut de l'ancrage OpenTimestamps : pending|confirmed|failed. */
        public ?string $otsStatus = null,
        /** Date de soumission aux calendars OpenTimestamps (ISO 8601). */
        public ?string $otsSubmittedAt = null,
        /** Date de confirmation de l'ancrage Bitcoin (ISO 8601). */
        public ?string $otsBitcoinConfirmedAt = null,
        /** Hauteur du bloc Bitcoin contenant l'ancrage. */
        public ?int $bitcoinBlockHeight = null,
        /** Receipt OpenTimestamps (.ots) encode en base64. */
        public ?string $otsProofBase64 = null,
    ) {}

    /**
     * Cree une instance a partir de la reponse API.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            isSealed: (bool) ($data['is_sealed'] ?? false),
            padesSignedAt: $data['pades_signed_at'] ?? null,
            signedPdfSha256: $data['signed_pdf_sha256'] ?? null,
            otsStatus: $data['ots_status'] ?? null,
            otsSubmittedAt: $data['ots_submitted_at'] ?? null,
            otsBitcoinConfirmedAt: $data['ots_bitcoin_confirmed_at'] ?? null,
            bitcoinBlockHeight: isset($data['bitcoin_block_height']) ? (int) $data['bitcoin_block_height'] : null,
            otsProofBase64: $data['ots_proof_base64'] ?? null,
        );
    }

    /**
     * Indique si l'ancrage Bitcoin a ete confirme.
     */
    public function isOtsConfirmed(): bool
    {
        return $this->otsStatus === 'confirmed';
    }

    /**
     * Indique si l'ancrage OpenTimestamps est encore en attente de confirmation.
     */
    public function isOtsPending(): bool
    {
        return $this->otsStatus === 'pending';
    }

    /**
     * Indique si l'ancrage OpenTimestamps a echoue.
     */
    public function isOtsFailed(): bool
    {
        return $this->otsStatus === 'failed';
    }
}
