<?php

namespace Cubepath\Services;

use Cubepath\CubepathClient;

class NetworkService
{
    private CubepathClient $client;

    public function __construct(CubepathClient $client)
    {
        $this->client = $client;
    }

    /**
     * List all private networks grouped by project.
     * Uses /projects/ endpoint (same as Go SDK).
     *
     * @return array
     */
    public function list(): array
    {
        return $this->client->get('/projects/');
    }

    /**
     * Create a new private network.
     *
     * @param array $params {
     *     @type string $name          Network name
     *     @type string $location_name Location (e.g., "us-mia-1")
     *     @type string $ip_range      IP range (e.g., "10.0.0.0")
     *     @type int    $prefix        Subnet prefix (e.g., 24)
     *     @type int    $project_id    Project ID
     *     @type string $label         Display label (optional)
     * }
     * @return array
     */
    public function create(array $params): array
    {
        return $this->client->post('/networks/create_network', $params);
    }

    /**
     * Update a private network.
     *
     * @param int   $networkId
     * @param array $params {
     *     @type string $name  New name (optional)
     *     @type string $label New label (optional)
     * }
     * @return array
     */
    public function update(int $networkId, array $params): array
    {
        return $this->client->put("/networks/{$networkId}", $params);
    }

    /**
     * Delete a private network.
     *
     * @param int $networkId
     * @return array
     */
    public function delete(int $networkId): array
    {
        return $this->client->delete("/networks/{$networkId}");
    }
}
