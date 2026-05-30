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

    /**
     * List static routes for a private network.
     *
     * @param int $networkId
     * @return array
     */
    public function listRoutes(int $networkId): array
    {
        return $this->client->get("/networks/{$networkId}/routes");
    }

    /**
     * Create a static route in a private network.
     *
     * @param int   $networkId
     * @param array $params {
     *     @type string $destination    CIDR destination (e.g., "192.168.1.0/24")
     *     @type string $next_hop_type  Next-hop type: "ip", "vps", or "baremetal"
     *     @type string $next_hop_target  IP address or resource identifier of the next hop
     *     @type string $description    Human-readable description (optional)
     * }
     * @return array Contains route id, detail
     */
    public function createRoute(int $networkId, array $params): array
    {
        return $this->client->post("/networks/{$networkId}/routes", $params);
    }

    /**
     * Delete a static route from a private network.
     *
     * @param int    $networkId
     * @param string $routeId
     * @return array
     */
    public function deleteRoute(int $networkId, string $routeId): array
    {
        return $this->client->delete("/networks/{$networkId}/routes/{$routeId}");
    }
}
