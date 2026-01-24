<?php

declare(strict_types=1);

namespace Scell\Sdk\Webhooks;

use Scell\Sdk\Enums\WebhookEvent;
use Scell\Sdk\Exceptions\ScellException;

/**
 * Verificateur de webhooks.
 *
 * Permet de verifier l'authenticite des webhooks recus.
 *
 * @example
 * ```php
 * // Dans votre controller de webhook
 * $verifier = new WebhookVerifier('whsec_...');
 *
 * try {
 *     $payload = $verifier->verify(
 *         file_get_contents('php://input'),
 *         $_SERVER['HTTP_X_SCELL_SIGNATURE']
 *     );
 *
 *     // Traiter le webhook
 *     $event = $payload['event'];
 *     $data = $payload['data'];
 * } catch (ScellException $e) {
 *     http_response_code(400);
 *     exit('Signature invalide');
 * }
 * ```
 */
class WebhookVerifier
{
    /**
     * Tolerance pour la verification du timestamp (5 minutes).
     */
    private const TIMESTAMP_TOLERANCE = 300;

    /**
     * Cree une instance du verificateur.
     *
     * @param string $secret Secret du webhook (commence par whsec_)
     */
    public function __construct(
        private readonly string $secret
    ) {}

    /**
     * Verifie la signature et retourne le payload.
     *
     * @param string $payload Corps de la requete (JSON brut)
     * @param string $signature Header X-Scell-Signature
     * @param int|null $tolerance Tolerance en secondes (defaut: 300)
     * @return array Payload decode
     * @throws ScellException Si la signature est invalide
     */
    public function verify(string $payload, string $signature, ?int $tolerance = null): array
    {
        $tolerance ??= self::TIMESTAMP_TOLERANCE;

        // Parser la signature: t=timestamp,v1=hash
        $parsed = $this->parseSignature($signature);

        if (!isset($parsed['t']) || !isset($parsed['v1'])) {
            throw new ScellException('Format de signature invalide');
        }

        $timestamp = (int) $parsed['t'];
        $expectedHash = $parsed['v1'];

        // Verifier le timestamp
        if ($tolerance > 0) {
            $now = time();
            if ($timestamp < ($now - $tolerance)) {
                throw new ScellException('Signature expiree');
            }
            if ($timestamp > ($now + $tolerance)) {
                throw new ScellException('Timestamp dans le futur');
            }
        }

        // Calculer le hash attendu
        $signedPayload = "{$timestamp}.{$payload}";
        $computedHash = hash_hmac('sha256', $signedPayload, $this->secret);

        // Comparaison securisee
        if (!hash_equals($computedHash, $expectedHash)) {
            throw new ScellException('Signature invalide');
        }

        // Decoder et retourner le payload
        $decoded = json_decode($payload, true);
        if ($decoded === null) {
            throw new ScellException('Payload JSON invalide');
        }

        return $decoded;
    }

    /**
     * Verifie la signature sans tolerance de timestamp.
     *
     * Utile pour les tests ou les replays.
     */
    public function verifyIgnoringTimestamp(string $payload, string $signature): array
    {
        return $this->verify($payload, $signature, 0);
    }

    /**
     * Verifie uniquement la signature (sans decoder).
     *
     * @return bool True si la signature est valide
     */
    public function isValid(string $payload, string $signature): bool
    {
        try {
            $this->verify($payload, $signature);
            return true;
        } catch (ScellException) {
            return false;
        }
    }

    /**
     * Parse le header de signature.
     *
     * @return array<string, string>
     */
    private function parseSignature(string $signature): array
    {
        $parts = explode(',', $signature);
        $parsed = [];

        foreach ($parts as $part) {
            $pair = explode('=', $part, 2);
            if (count($pair) === 2) {
                $parsed[trim($pair[0])] = trim($pair[1]);
            }
        }

        return $parsed;
    }

    /**
     * Genere une signature pour les tests.
     *
     * @param string $payload Payload JSON
     * @param int|null $timestamp Timestamp (defaut: now)
     * @return string Signature formatee
     */
    public function generateSignature(string $payload, ?int $timestamp = null): string
    {
        $timestamp ??= time();
        $signedPayload = "{$timestamp}.{$payload}";
        $hash = hash_hmac('sha256', $signedPayload, $this->secret);

        return "t={$timestamp},v1={$hash}";
    }
}

/**
 * Webhook payload parse.
 */
readonly class WebhookPayload
{
    public function __construct(
        public WebhookEvent $event,
        public string $timestamp,
        public array $data,
        public ?string $deliveryId = null,
    ) {}

    /**
     * Cree une instance a partir du payload decode.
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            event: WebhookEvent::from($payload['event']),
            timestamp: $payload['timestamp'],
            data: $payload['data'] ?? [],
            deliveryId: $payload['delivery_id'] ?? null,
        );
    }

    /**
     * Verifie si c'est un evenement de facture.
     */
    public function isInvoiceEvent(): bool
    {
        return $this->event->domain() === 'invoice';
    }

    /**
     * Verifie si c'est un evenement de signature.
     */
    public function isSignatureEvent(): bool
    {
        return $this->event->domain() === 'signature';
    }

    /**
     * Verifie si c'est un evenement de solde.
     */
    public function isBalanceEvent(): bool
    {
        return $this->event->domain() === 'balance';
    }
}
