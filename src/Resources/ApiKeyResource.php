<?php

declare(strict_types=1);

namespace Scell\Sdk\Resources;

use Scell\Sdk\DTOs\ApiKey;
use Scell\Sdk\Http\HttpClient;

class ApiKeyResource
{
    public function __construct(
        private readonly HttpClient $http
    ) {}

    public function list(): array
    {
        $response = $this->http->get('api-keys');
        return array_map(fn(array $data) => ApiKey::fromArray($data), $response['data'] ?? []);
    }

    public function create(array $data): ApiKey
    {
        $response = $this->http->post('api-keys', $data);
        return ApiKey::fromArray($response['data']);
    }

    public function delete(string $id): void
    {
        $this->http->delete("api-keys/{$id}");
    }
}
