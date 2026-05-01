<?php

namespace Cubepath\Services;

use Cubepath\CubepathClient;

class BaremetalService
{
    private CubepathClient $client;

    public function __construct(CubepathClient $client)
    {
        $this->client = $client;
    }

    /**
     * Deploy a new baremetal server.
     *
     * @param int   $projectId
     * @param array $params {
     *     @type string $model_name      Server model name
     *     @type string $location_name   Location
     *     @type string $hostname        Hostname
     *     @type string $password        Root password
     *     @type string $label           Display label (optional)
     *     @type string $user            Default user (optional)
     *     @type int[]  $ssh_key_ids     SSH key IDs (optional)
     *     @type string $os_name         OS name (optional)
     *     @type string $disk_layout_name Disk layout (optional)
     * }
     * @return array Task response
     */
    public function deploy(int $projectId, array $params): array
    {
        return $this->client->post("/baremetal/deploy/{$projectId}", $params);
    }

    /**
     * List all baremetal servers grouped by project.
     *
     * @return array
     */
    public function list(): array
    {
        return $this->client->get('/projects/');
    }

    /**
     * Get a single baremetal server by ID.
     * Iterates through all projects to find the server.
     *
     * @param int $baremetalId
     * @return array
     * @throws \RuntimeException if not found
     */
    public function get(int $baremetalId): array
    {
        $projects = $this->list();
        foreach ($projects as $project) {
            if (isset($project['baremetals']) && is_array($project['baremetals'])) {
                foreach ($project['baremetals'] as $bm) {
                    if (isset($bm['id']) && $bm['id'] === $baremetalId) {
                        return $bm;
                    }
                }
            }
        }
        throw new \RuntimeException("Baremetal server {$baremetalId} not found");
    }

    /**
     * Update a baremetal server.
     *
     * @param int   $baremetalId
     * @param array $params {
     *     @type string $hostname New hostname (optional)
     *     @type string $label    New label (optional)
     *     @type string $tags     Tags (optional)
     * }
     * @return array
     */
    public function update(int $baremetalId, array $params): array
    {
        return $this->client->patch("/baremetal/update/{$baremetalId}", $params);
    }

    /**
     * Control baremetal power state.
     *
     * @param int    $baremetalId
     * @param string $action One of: start, stop, reboot, reset
     * @return array
     */
    public function power(int $baremetalId, string $action): array
    {
        return $this->client->post("/baremetal/{$baremetalId}/power/{$action}");
    }

    /**
     * Activate rescue mode.
     *
     * @param int $baremetalId
     * @return array Contains detail, username, password
     */
    public function rescue(int $baremetalId): array
    {
        return $this->client->post("/baremetal/{$baremetalId}/rescue");
    }

    /**
     * Reset BMC (IPMI controller).
     *
     * @param int $baremetalId
     * @return array
     */
    public function resetBMC(int $baremetalId): array
    {
        return $this->client->post("/baremetal/{$baremetalId}/reset-bmc");
    }

    /**
     * Get BMC sensor data (temperatures, fans).
     *
     * @param int $baremetalId
     * @return array Contains node, ipmi_available, power_on, sensors
     */
    public function bmcSensors(int $baremetalId): array
    {
        return $this->client->get("/baremetal/{$baremetalId}/bmc-sensors");
    }

    /**
     * Create an IPMI proxy session for remote console.
     *
     * @param int $baremetalId
     * @return array Contains proxy_url, credentials
     */
    public function ipmiSession(int $baremetalId): array
    {
        return $this->client->post("/ipmi-proxy/create-session/{$baremetalId}");
    }

    /**
     * Reinstall OS on baremetal server.
     *
     * @param int   $baremetalId
     * @param array $params {
     *     @type string $os_name          OS name
     *     @type string $password         Root password
     *     @type string $disk_layout_name Disk layout (optional)
     *     @type string $user             Default user (optional)
     *     @type string $hostname         Hostname (optional)
     *     @type int[]  $ssh_key_ids      SSH key IDs (optional)
     * }
     * @return array
     */
    public function reinstall(int $baremetalId, array $params): array
    {
        return $this->client->post("/baremetal/{$baremetalId}/reinstall", $params);
    }

    /**
     * Get reinstallation status.
     *
     * @param int $baremetalId
     * @return array Contains is_reinstalling, status, os_name
     */
    public function reinstallStatus(int $baremetalId): array
    {
        return $this->client->get("/baremetal/{$baremetalId}/reinstall/status");
    }

    /**
     * Enable monitoring for a baremetal server.
     *
     * @param int $baremetalId
     * @return array
     */
    public function monitoringEnable(int $baremetalId): array
    {
        return $this->client->put("/baremetal/{$baremetalId}/monitoring?enable=true");
    }

    /**
     * Disable monitoring for a baremetal server.
     *
     * @param int $baremetalId
     * @return array
     */
    public function monitoringDisable(int $baremetalId): array
    {
        return $this->client->put("/baremetal/{$baremetalId}/monitoring?enable=false");
    }
}
