<?php

namespace Cubepath\Services;

use Cubepath\CubepathClient;

/**
 * VPS availability groups: VPS in the same group are spread across different hosts.
 */
class AvailabilityGroupService
{
    private CubepathClient $client;

    public function __construct(CubepathClient $client)
    {
        $this->client = $client;
    }

    /**
     * Create an availability group.
     *
     * @param array $params {
     *     @type int    $project_id    Project ID (required)
     *     @type string $name          Group name (required)
     *     @type string $location_name Location (required)
     *     @type string $description   (optional)
     * }
     * @return array Contains detail, uuid, project_id, name, description, strategy, location_name,
     *               max_servers, vps_count
     */
    public function create(array $params): array
    {
        return $this->client->post('/vps/availability-groups/', $params);
    }

    /**
     * List the availability groups of a project.
     *
     * @param int         $projectId
     * @param string|null $locationName Only groups in this location (optional)
     * @return array Contains groups[]
     */
    public function list(int $projectId, ?string $locationName = null): array
    {
        $query = $locationName !== null && $locationName !== '' ? ['location_name' => $locationName] : [];
        return $this->client->get("/vps/availability-groups/project/{$projectId}", $query);
    }

    /**
     * Get an availability group with its VPS.
     *
     * @param string $groupUUID
     * @return array Contains uuid, project_id, name, description, strategy, location_name,
     *               max_servers, vps_count, vps_list, created_at
     */
    public function get(string $groupUUID): array
    {
        return $this->client->get("/vps/availability-groups/{$groupUUID}");
    }

    /**
     * Delete an empty availability group.
     *
     * @param string $groupUUID
     * @return array
     */
    public function delete(string $groupUUID): array
    {
        return $this->client->delete("/vps/availability-groups/{$groupUUID}");
    }

    /**
     * Move an availability group to another project.
     *
     * @param string $groupUUID
     * @param int    $projectId Destination project ID
     * @return array
     */
    public function moveToProject(string $groupUUID, int $projectId): array
    {
        return $this->client->post("/vps/availability-groups/{$groupUUID}/move-project", [
            'project_id' => $projectId,
        ]);
    }

    /**
     * Add a VPS of the same location to the group.
     *
     * @param string $groupUUID
     * @param int    $vpsId
     * @return array
     */
    public function addVPS(string $groupUUID, int $vpsId): array
    {
        return $this->client->post("/vps/availability-groups/{$groupUUID}/vps/{$vpsId}");
    }

    /**
     * Remove a VPS from the group.
     *
     * @param string $groupUUID
     * @param int    $vpsId
     * @return array
     */
    public function removeVPS(string $groupUUID, int $vpsId): array
    {
        return $this->client->delete("/vps/availability-groups/{$groupUUID}/vps/{$vpsId}");
    }
}
