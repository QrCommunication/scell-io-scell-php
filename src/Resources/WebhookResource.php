<?php

declare(strict_types=1);

namespace Scell\Sdk\Resources;

use Scell\Sdk\DTOs\Webhook;
use Scell\Sdk\Enums\Environment;
use Scell\Sdk\Enums\WebhookEvent;
use Scell\Sdk\Http\HttpClient;

/**
 * Resource pour les webhooks.
 *
 * Permet de configurer les webhooks pour recevoir les evenements en temps reel.
 */
class WebhookResource
{
    public function __construct(
        private readonly HttpClient $http
    ) {}

    /**
     * Liste tous les webhooks.
     *
     * @param string|null $companyId Filtrer par entreprise
     * @return Webhook[]
     */
    public function list(?string $companyId = null): array
    {
        $query = [];
        if ($companyId !== null) {
            $query['company_id'] = $companyId;
        }

        $response = $this->http->get('webhooks', $query);
        return array_map(
            fn(array $data) => Webhook::fromArray($data),
            $response['data'] ?? []
        );
    }

    /**
     * Recupere un webhook par son ID.
     */
    public function get(string $id): Webhook
    {
        $response = $this->http->get("webhooks/{$id}");
        return Webhook::fromArray($response['data']);
    }

    /**
     * Cree un nouveau webhook.
     *
     * @param array{
     *     url: string,
     *     events: WebhookEvent[]|string[],
     *     environment: Environment|string,
     *     headers?: array<string, string>,
     *     retry_count?: int,
     *     timeout_seconds?: int
     * } $data
     */
    public function create(array $data): Webhook
    {
        $payload = $this->normalizePayload($data);
        $response = $this->http->post('webhooks', $payload);
        return Webhook::fromArray($response['data']);
    }

    /**
     * Cree un webhook avec le builder fluent.
     */
    public function builder(): WebhookBuilder
    {
        return new WebhookBuilder($this);
    }

    /**
     * Met a jour un webhook.
     *
     * @param string $id ID du webhook
     * @param array<string, mixed> $data Donnees a mettre a jour
     */
    public function update(string $id, array $data): Webhook
    {
        $payload = $this->normalizePayload($data);
        $response = $this->http->put("webhooks/{$id}", $payload);
        return Webhook::fromArray($response['data']);
    }

    /**
     * Supprime un webhook.
     *
     * @return array{message: string}
     */
    public function delete(string $id): array
    {
        return $this->http->delete("webhooks/{$id}");
    }

    /**
     * Regenere le secret d'un webhook.
     */
    public function regenerateSecret(string $id): Webhook
    {
        $response = $this->http->post("webhooks/{$id}/regenerate-secret");
        return Webhook::fromArray($response['data']);
    }

    /**
     * Envoie un evenement de test.
     *
     * @return array{success: bool, status_code?: int, response_time_ms?: int, error?: string}
     */
    public function test(string $id): array
    {
        return $this->http->post("webhooks/{$id}/test");
    }

    /**
     * Recupere les logs d'un webhook.
     *
     * @param int $perPage Nombre d'elements par page
     * @return array{data: array[], meta: array}
     */
    public function logs(string $id, int $perPage = 25): array
    {
        return $this->http->get("webhooks/{$id}/logs", ['per_page' => $perPage]);
    }

    /**
     * Active un webhook.
     */
    public function enable(string $id): Webhook
    {
        return $this->update($id, ['is_active' => true]);
    }

    /**
     * Desactive un webhook.
     */
    public function disable(string $id): Webhook
    {
        return $this->update($id, ['is_active' => false]);
    }

    /**
     * Normalise le payload.
     */
    private function normalizePayload(array $data): array
    {
        $payload = [];

        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }

            if ($key === 'events') {
                $payload['events'] = array_map(
                    fn($event) => $event instanceof WebhookEvent ? $event->value : $event,
                    $value
                );
            } elseif ($key === 'environment' && $value instanceof Environment) {
                $payload['environment'] = $value->value;
            } else {
                $payload[$key] = $value;
            }
        }

        return $payload;
    }
}

/**
 * Builder fluent pour creer des webhooks.
 */
class WebhookBuilder
{
    private array $data = [];
    private array $events = [];
    private array $headers = [];

    public function __construct(
        private readonly WebhookResource $resource
    ) {}

    public function url(string $url): self
    {
        $this->data['url'] = $url;
        return $this;
    }

    /**
     * Ajoute un evenement a ecouter.
     */
    public function onEvent(WebhookEvent $event): self
    {
        $this->events[] = $event;
        return $this;
    }

    /**
     * Ecoute tous les evenements de facture.
     */
    public function onAllInvoiceEvents(): self
    {
        foreach (WebhookEvent::forDomain('invoice') as $event) {
            $this->events[] = $event;
        }
        return $this;
    }

    /**
     * Ecoute tous les evenements de signature.
     */
    public function onAllSignatureEvents(): self
    {
        foreach (WebhookEvent::forDomain('signature') as $event) {
            $this->events[] = $event;
        }
        return $this;
    }

    /**
     * Ecoute tous les evenements de solde.
     */
    public function onBalanceEvents(): self
    {
        foreach (WebhookEvent::forDomain('balance') as $event) {
            $this->events[] = $event;
        }
        return $this;
    }

    /**
     * Ecoute tous les evenements.
     */
    public function onAllEvents(): self
    {
        $this->events = WebhookEvent::cases();
        return $this;
    }

    public function environment(Environment $env): self
    {
        $this->data['environment'] = $env;
        return $this;
    }

    public function production(): self
    {
        return $this->environment(Environment::Production);
    }

    public function sandbox(): self
    {
        return $this->environment(Environment::Sandbox);
    }

    /**
     * Ajoute un header personnalise.
     */
    public function addHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function retryCount(int $count): self
    {
        $this->data['retry_count'] = min(5, max(0, $count));
        return $this;
    }

    public function timeoutSeconds(int $seconds): self
    {
        $this->data['timeout_seconds'] = min(60, max(5, $seconds));
        return $this;
    }

    /**
     * Cree le webhook.
     */
    public function create(): Webhook
    {
        if (empty($this->events)) {
            throw new \InvalidArgumentException('Au moins un evenement est requis');
        }

        if (!isset($this->data['url'])) {
            throw new \InvalidArgumentException('L\'URL est requise');
        }

        if (!isset($this->data['environment'])) {
            $this->data['environment'] = Environment::Production;
        }

        $this->data['events'] = $this->events;

        if (!empty($this->headers)) {
            $this->data['headers'] = $this->headers;
        }

        return $this->resource->create($this->data);
    }
}
