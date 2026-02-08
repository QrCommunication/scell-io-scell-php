<?php

declare(strict_types=1);

namespace Scell\Sdk\Resources;

use Scell\Sdk\DTOs\StatsMonthly;
use Scell\Sdk\DTOs\StatsOverview;
use Scell\Sdk\Http\HttpClient;

class StatsResource
{
    public function __construct(
        private readonly HttpClient $http
    ) {}

    public function overview(array $params = []): StatsOverview
    {
        $response = $this->http->get('tenant/stats/overview', $params);
        return StatsOverview::fromArray($response['data']);
    }

    public function monthly(array $params = []): array
    {
        $response = $this->http->get('tenant/stats/monthly', $params);
        return array_map(fn(array $data) => StatsMonthly::fromArray($data), $response['data'] ?? []);
    }

    public function subTenantOverview(string $subTenantId, array $params = []): StatsOverview
    {
        $response = $this->http->get("tenant/sub-tenants/{$subTenantId}/stats/overview", $params);
        return StatsOverview::fromArray($response['data']);
    }
}
