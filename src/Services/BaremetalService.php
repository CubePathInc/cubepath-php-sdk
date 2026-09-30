<?php

namespace Cubepath\Services;

use Cubepath\APIError;
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
     *     @type int    $network_id      Private network to attach (optional)
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
     * List every baremetal server of the organization as a flat list, each with its project.
     *
     * @return array List of servers (id, hostname, status, location, os, baremetal_model,
     *               floating_ips, ssh_keys, project, network, protected, has_ipmi, has_kvm, ...)
     */
    public function listAll(): array
    {
        return $this->client->get('/baremetal/');
    }

    /**
     * List the server models available to deploy, per location.
     *
     * @return array Contains locations[]
     */
    public function models(): array
    {
        return $this->client->get('/baremetal/models');
    }

    /**
     * List the operating systems and disk layouts that can be installed on a server.
     *
     * @param int $baremetalId
     * @return array List of options (id, os_name, operating_system, disk_layouts)
     */
    public function listOS(int $baremetalId): array
    {
        return $this->client->get("/baremetal/os/{$baremetalId}");
    }

    /**
     * Get the KVM console access of a server that has one.
     *
     * @param int $baremetalId
     * @return array Contains url, username, password, updated_at
     */
    public function kvm(int $baremetalId): array
    {
        return $this->client->get("/baremetal/{$baremetalId}/kvm");
    }

    /**
     * Enable or disable delete protection.
     *
     * @param int  $baremetalId
     * @param bool $enabled
     * @return array
     */
    public function protection(int $baremetalId, bool $enabled): array
    {
        return $this->client->post("/baremetal/{$baremetalId}/protection", [
            'enabled' => $enabled,
        ]);
    }

    /**
     * Move a baremetal server to another project of the organization.
     *
     * @param int $baremetalId
     * @param int $projectId Destination project ID
     * @return array
     */
    public function moveToProject(int $baremetalId, int $projectId): array
    {
        return $this->client->post("/baremetal/{$baremetalId}/move-project", [
            'project_id' => $projectId,
        ]);
    }

    /**
     * Add SSH keys to a baremetal server.
     *
     * @param int   $baremetalId
     * @param int[] $sshKeyIds
     * @return array
     */
    public function addSSHKeys(int $baremetalId, array $sshKeyIds): array
    {
        return $this->client->post("/baremetal/{$baremetalId}/ssh-keys", array_values(array_map('intval', $sshKeyIds)));
    }

    /**
     * Remove an SSH key from a baremetal server.
     *
     * @param int $baremetalId
     * @param int $sshKeyId
     * @return array
     */
    public function removeSSHKey(int $baremetalId, int $sshKeyId): array
    {
        return $this->client->delete("/baremetal/{$baremetalId}/ssh-keys/{$sshKeyId}");
    }

    /**
     * Attach the server to a private network in its location.
     *
     * @param int $baremetalId
     * @param int $networkId
     * @return array
     */
    public function attachNetwork(int $baremetalId, int $networkId): array
    {
        return $this->client->post("/baremetal/{$baremetalId}/network", [
            'network_id' => $networkId,
        ]);
    }

    /**
     * Detach the server from its private network.
     *
     * @param int $baremetalId
     * @return array
     */
    public function detachNetwork(int $baremetalId): array
    {
        return $this->client->delete("/baremetal/{$baremetalId}/network");
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
     * Temperatures and fan speeds from the last BMC poll, served through GraphQL.
     *
     * @param int $baremetalId
     * @return array Contains ipmi_available, power_on, last_seen (Unix time or null) and
     *               sensors.temperatures / sensors.fans (name, value, unit CELSIUS or RPM).
     *               node is kept for compatibility and is always empty.
     * @throws APIError 404 when the server does not exist
     */
    public function bmcSensors(int $baremetalId): array
    {
        $data = $this->client->graphql(
            'query($id: ID!) { baremetal(id: $id) { sensors { ipmiAvailable powerOn lastSeen temperatures { name value unit } fans { name value unit } } } }',
            ['id' => (string) $baremetalId]
        );
        if (empty($data['baremetal'])) {
            throw new APIError(404, "Baremetal {$baremetalId} not found");
        }
        $s = $data['baremetal']['sensors'];
        return [
            'node' => '',
            'ipmi_available' => (bool) ($s['ipmiAvailable'] ?? false),
            'power_on' => (bool) ($s['powerOn'] ?? false),
            'last_seen' => $s['lastSeen'] ?? null,
            'sensors' => [
                'temperatures' => $s['temperatures'] ?? [],
                'fans' => $s['fans'] ?? [],
            ],
        ];
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
     * Whether an OS reinstallation is running. There is no dedicated endpoint any more: a
     * server is reinstalling while its status is "deploying".
     *
     * @param int $baremetalId
     * @return array Contains is_reinstalling, status, os_name (always empty, kept for compatibility)
     */
    public function reinstallStatus(int $baremetalId): array
    {
        $bm = $this->get($baremetalId);
        $status = $bm['status'] ?? '';
        return ['is_reinstalling' => $status === 'deploying', 'status' => $status, 'os_name' => ''];
    }

    /**
     * Cancel a pending or running OS reinstallation.
     *
     * @param int $baremetalId
     * @return array
     */
    public function cancelReinstall(int $baremetalId): array
    {
        return $this->client->delete("/baremetal/{$baremetalId}/reinstall");
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
