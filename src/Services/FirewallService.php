<?php

namespace Cubepath\Services;

use Cubepath\CubepathClient;

class FirewallService
{
    private CubepathClient $client;

    public function __construct(CubepathClient $client)
    {
        $this->client = $client;
    }

    /**
     * List all firewall groups.
     *
     * @return array
     */
    public function list(): array
    {
        return $this->client->get('/firewall/groups');
    }

    /**
     * Get a firewall group by ID.
     *
     * @param int $groupId
     * @return array
     */
    public function get(int $groupId): array
    {
        return $this->client->get("/firewall/groups/{$groupId}");
    }

    /**
     * Create a new firewall group.
     *
     * @param array $params {
     *     @type string $name    Group name
     *     @type array  $rules   Array of firewall rules
     *     @type bool   $enabled Enable group
     * }
     * @return array
     */
    public function create(array $params): array
    {
        return $this->client->post('/firewall/groups', $params);
    }

    /**
     * Update a firewall group.
     *
     * @param int   $groupId
     * @param array $params
     * @return array
     */
    public function update(int $groupId, array $params): array
    {
        return $this->client->patch("/firewall/groups/{$groupId}", $params);
    }

    /**
     * Delete a firewall group.
     *
     * @param int $groupId
     * @return array
     */
    public function delete(int $groupId): array
    {
        return $this->client->delete("/firewall/groups/{$groupId}");
    }

    /**
     * Assign/unassign firewall groups to a VPS.
     *
     * @param int   $vpsId
     * @param array $firewallGroupIds Array of firewall group IDs
     * @return array Contains message, vps_id, firewall_groups, sync_task_created
     */
    public function assignToVPS(int $vpsId, array $firewallGroupIds): array
    {
        return $this->client->post("/vps/{$vpsId}/firewall-groups", [
            'firewall_group_ids' => $firewallGroupIds,
        ]);
    }
}
