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

    /**
     * Move a private network to another project of the organization.
     *
     * @param int $networkId
     * @param int $projectId Destination project ID
     * @return array
     */
    public function moveToProject(int $networkId, int $projectId): array
    {
        return $this->client->post("/networks/{$networkId}/move-project", [
            'project_id' => $projectId,
        ]);
    }

    // --- BGP peers ---

    /**
     * List the BGP peers of a private network, with their session state.
     *
     * @param int $networkId
     * @return array List of peers (id, peer_type, peer_target, remote_asn, max_prefix, description,
     *               enabled, resolved_peer_ip, last_state, prefixes_received, received_prefixes, ...)
     */
    public function listBGPPeers(int $networkId): array
    {
        return $this->client->get("/networks/{$networkId}/bgp-peers");
    }

    /**
     * Create a BGP peer: a VPS, baremetal server or IP of the network that announces routes.
     *
     * @param int   $networkId
     * @param array $params {
     *     @type string $peer_type   "ip", "vps" or "baremetal" (required)
     *     @type string $peer_target Private IP, or the VPS or baremetal ID (required)
     *     @type int    $remote_asn  ASN of the peer, not 64512 (required)
     *     @type int    $max_prefix  Max prefixes accepted (optional, default 100)
     *     @type string $description (optional)
     * }
     * @return array Contains detail, peer_id, peer_type, peer_target, remote_asn
     */
    public function createBGPPeer(int $networkId, array $params): array
    {
        return $this->client->post("/networks/{$networkId}/bgp-peers", $params);
    }

    /**
     * Update a BGP peer.
     *
     * @param int    $networkId
     * @param string $peerId
     * @param array  $params {
     *     @type int    $max_prefix  (optional)
     *     @type string $description (optional)
     *     @type bool   $enabled     (optional)
     * }
     * @return array
     */
    public function updateBGPPeer(int $networkId, string $peerId, array $params): array
    {
        return $this->client->patch("/networks/{$networkId}/bgp-peers/{$peerId}", $params);
    }

    /**
     * Delete a BGP peer.
     *
     * @param int    $networkId
     * @param string $peerId
     * @return array
     */
    public function deleteBGPPeer(int $networkId, string $peerId): array
    {
        return $this->client->delete("/networks/{$networkId}/bgp-peers/{$peerId}");
    }
}
