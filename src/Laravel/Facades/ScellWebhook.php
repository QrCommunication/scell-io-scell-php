<?php

declare(strict_types=1);

namespace Scell\Sdk\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Scell\Sdk\Webhooks\WebhookVerifier;

/**
 * Facade Laravel pour le verificateur de webhooks.
 *
 * @method static array verify(string $payload, string $signature, ?int $tolerance = null)
 * @method static array verifyIgnoringTimestamp(string $payload, string $signature)
 * @method static bool isValid(string $payload, string $signature)
 * @method static string generateSignature(string $payload, ?int $timestamp = null)
 *
 * @see \Scell\Sdk\Webhooks\WebhookVerifier
 *
 * @example
 * ```php
 * use Scell\Sdk\Laravel\Facades\ScellWebhook;
 * use Illuminate\Http\Request;
 *
 * // Dans un controller
 * public function handleWebhook(Request $request)
 * {
 *     try {
 *         $payload = ScellWebhook::verify(
 *             $request->getContent(),
 *             $request->header('X-Scell-Signature')
 *         );
 *
 *         $event = $payload['event'];
 *         $data = $payload['data'];
 *
 *         // Traiter selon l'evenement
 *         match ($event) {
 *             'invoice.validated' => $this->handleInvoiceValidated($data),
 *             'signature.completed' => $this->handleSignatureCompleted($data),
 *             default => null,
 *         };
 *
 *         return response()->json(['received' => true]);
 *     } catch (\Scell\Sdk\Exceptions\ScellException $e) {
 *         return response()->json(['error' => 'Invalid signature'], 400);
 *     }
 * }
 * ```
 */
class ScellWebhook extends Facade
{
    /**
     * Retourne le nom du binding dans le container.
     */
    protected static function getFacadeAccessor(): string
    {
        return WebhookVerifier::class;
    }
}
