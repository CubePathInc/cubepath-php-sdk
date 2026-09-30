<?php

namespace Cubepath\Services;

use Cubepath\APIError;
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
     * Get a firewall group by ID. The API has no single-group endpoint, so the group is
     * looked up in the list.
     *
     * @param int $groupId
     * @return array
     * @throws APIError 404 when the group does not exist
     */
    public function get(int $groupId): array
    {
        foreach ($this->list() as $group) {
            if ((int) ($group['id'] ?? 0) === $groupId) {
                return $group;
            }
        }
        throw new APIError(404, "Firewall group {$groupId} not found");
    }

    /**
     * Create a new firewall group.
     *
     * @param array $params {
     *     @type int    $project_id Project the group belongs to (required)
     *     @type string $name       Group name
     *     @type array  $rules      Array of firewall rules
     *     @type bool   $enabled    Enable group
     * }
     * @return array
     */
    public function create(array $params): array
    {
        if (empty($params['project_id'])) {
            throw new \InvalidArgumentException('project_id is required to create a firewall group');
        }
        $projectId = (int) $params['project_id'];
        unset($params['project_id']);
        return $this->client->post("/firewall/groups?project_id={$projectId}", $params);
    }

    /**
     * Update the name, rules or enabled flag of a firewall group; omitted keys are left
     * unchanged.
     *
     * @param int   $groupId
     * @param array $params
     * @return array
     */
    public function update(int $groupId, array $params): array
    {
        return $this->client->put("/firewall/groups/{$groupId}", $params);
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
     * Replace the firewall groups of a VPS (at most 10, in priority order). An empty array
     * removes them all. The new rules are applied in the background.
     *
     * @param int   $vpsId
     * @param array $firewallGroupIds Array of firewall group IDs
     * @return array Contains detail, vps_id, firewall_groups, sync_task_created
     */
    public function assignToVPS(int $vpsId, array $firewallGroupIds): array
    {
        return $this->client->put("/firewall/vps/{$vpsId}/groups", [
            'firewall_group_ids' => $firewallGroupIds,
        ]);
    }
}
