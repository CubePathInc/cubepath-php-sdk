<?php

namespace Cubepath\Services;

use Cubepath\APIError;
use Cubepath\BaremetalRef;
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
     * Get a single baremetal server by uuid.
     * Iterates through all projects to find the server whose "id" is that uuid.
     *
     * @param string $baremetalId Server uuid
     * @return array
     * @throws \RuntimeException if not found
     */
    public function get(string $baremetalId): array
    {
        $ref = BaremetalRef::normalize($baremetalId);
        $projects = $this->list();
        foreach ($projects as $project) {
            if (isset($project['baremetals']) && is_array($project['baremetals'])) {
                foreach ($project['baremetals'] as $bm) {
                    if (is_array($bm) && BaremetalRef::matches($bm, $ref)) {
                        return $bm;
                    }
                }
            }
        }
        throw new \RuntimeException("Baremetal server {$ref} not found");
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
     * @param string $baremetalId Server uuid
     * @return array List of options (id, os_name, operating_system, disk_layouts)
     */
    public function listOS(string $baremetalId): array
    {
        $ref = BaremetalRef::normalize($baremetalId);
        return $this->client->get("/baremetal/os/{$ref}");
    }

    /**
     * Get the KVM console access of a server that has one.
     *
     * @param string $baremetalId Server uuid
     * @return array Contains url, username, password, updated_at
     */
    public function kvm(string $baremetalId): array
    {
        $ref = BaremetalRef::normalize($baremetalId);
        return $this->client->get("/baremetal/{$ref}/kvm");
    }

    /**
     * Enable or disable delete protection.
     *
     * @param int  $baremetalId
     * @param bool $enabled
     * @return array
     */
    public function protection(string $baremetalId, bool $enabled): array
    {
        $ref = BaremetalRef::normalize($baremetalId);
        return $this->client->post("/baremetal/{$ref}/protection", [
            'enabled' => $enabled,
        ]);
    }

    /**
     * Move a baremetal server to another project of the organization.
     *
     * @param string $baremetalId Server uuid
     * @param int $projectId Destination project ID
     * @return array
     */
    public function moveToProject(string $baremetalId, int $projectId): array
    {
        $ref = BaremetalRef::normalize($baremetalId);
        return $this->client->post("/baremetal/{$ref}/move-project", [
            'project_id' => $projectId,
        ]);
    }

    /**
     * Add SSH keys to a baremetal server.
     *
     * @param int|string $baremetalId Server number or uuid
     * @param int[] $sshKeyIds
     * @return array
     */
    public function addSSHKeys(string $baremetalId, array $sshKeyIds): array
    {
        $ref = BaremetalRef::normalize($baremetalId);
        return $this->client->post("/baremetal/{$ref}/ssh-keys", array_values(array_map('intval', $sshKeyIds)));
    }

    /**
     * Remove an SSH key from a baremetal server.
     *
     * @param string $baremetalId Server uuid
     * @param int $sshKeyId
     * @return array
     */
    public function removeSSHKey(string $baremetalId, int $sshKeyId): array
    {
        $ref = BaremetalRef::normalize($baremetalId);
        return $this->client->delete("/baremetal/{$ref}/ssh-keys/{$sshKeyId}");
    }

    /**
     * Attach the server to a private network in its location.
     *
     * @param string $baremetalId Server uuid
     * @param int $networkId
     * @return array
     */
    public function attachNetwork(string $baremetalId, int $networkId): array
    {
        $ref = BaremetalRef::normalize($baremetalId);
        return $this->client->post("/baremetal/{$ref}/network", [
            'network_id' => $networkId,
        ]);
    }

    /**
     * Detach the server from its private network.
     *
     * @param string $baremetalId Server uuid
     * @return array
     */
    public function detachNetwork(string $baremetalId): array
    {
        $ref = BaremetalRef::normalize($baremetalId);
        return $this->client->delete("/baremetal/{$ref}/network");
    }

    /**
     * Update a baremetal server.
     *
     * @param int|string $baremetalId Server number or uuid
     * @param array $params {
     *     @type string $hostname New hostname (optional)
     *     @type string $label    New label (optional)
     *     @type string $tags     Tags (optional)
     * }
     * @return array
     */
    public function update(string $baremetalId, array $params): array
    {
        $ref = BaremetalRef::normalize($baremetalId);
        return $this->client->patch("/baremetal/update/{$ref}", $params);
    }

    /**
     * Control baremetal power state.
     *
     * @param int|string $baremetalId Server number or uuid
     * @param string $action One of: start, stop, reboot, reset
     * @return array
     */
    public function power(string $baremetalId, string $action): array
    {
        $ref = BaremetalRef::normalize($baremetalId);
        return $this->client->post("/baremetal/{$ref}/power/{$action}");
    }

    /**
     * Activate rescue mode.
     *
     * @param string $baremetalId Server uuid
     * @return array Contains detail, username, password
     */
    public function rescue(string $baremetalId): array
    {
        $ref = BaremetalRef::normalize($baremetalId);
        return $this->client->post("/baremetal/{$ref}/rescue");
    }

    /**
     * Reset BMC (IPMI controller).
     *
     * @param string $baremetalId Server uuid
     * @return array
     */
    public function resetBMC(string $baremetalId): array
    {
        $ref = BaremetalRef::normalize($baremetalId);
        return $this->client->post("/baremetal/{$ref}/reset-bmc");
    }

    /**
     * Temperatures and fan speeds from the last BMC poll, served through GraphQL.
     *
     * @param string $baremetalId Server uuid
     * @return array Contains ipmi_available, power_on, last_seen (Unix time or null) and
     *               sensors.temperatures / sensors.fans (name, value, unit CELSIUS or RPM).
     *               node is kept for compatibility and is always empty.
     * @throws APIError 404 when the server does not exist
     */
    public function bmcSensors(string $baremetalId): array
    {
        $ref = BaremetalRef::normalize($baremetalId);
        $data = $this->client->graphql(
            'query($id: ID!) { baremetal(id: $id) { sensors { ipmiAvailable powerOn lastSeen temperatures { name value unit } fans { name value unit } } } }',
            ['id' => $ref]
        );
        if (empty($data['baremetal'])) {
            throw new APIError(404, "Baremetal {$ref} not found");
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
     * @param string $baremetalId Server uuid
     * @return array Contains proxy_url, credentials
     */
    public function ipmiSession(string $baremetalId): array
    {
        $ref = BaremetalRef::normalize($baremetalId);
        return $this->client->post("/ipmi-proxy/create-session/{$ref}");
    }

    /**
     * Reinstall OS on baremetal server.
     *
     * @param int|string $baremetalId Server number or uuid
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
    public function reinstall(string $baremetalId, array $params): array
    {
        $ref = BaremetalRef::normalize($baremetalId);
        return $this->client->post("/baremetal/{$ref}/reinstall", $params);
    }

    /**
     * Whether an OS reinstallation is running. There is no dedicated endpoint any more: a
     * server is reinstalling while its status is "deploying".
     *
     * @param string $baremetalId Server uuid
     * @return array Contains is_reinstalling, status, os_name (always empty, kept for compatibility)
     */
    public function reinstallStatus(string $baremetalId): array
    {
        $bm = $this->get($baremetalId);
        $status = $bm['status'] ?? '';
        return ['is_reinstalling' => $status === 'deploying', 'status' => $status, 'os_name' => ''];
    }

    /**
     * Cancel a pending or running OS reinstallation.
     *
     * @param string $baremetalId Server uuid
     * @return array
     */
    public function cancelReinstall(string $baremetalId): array
    {
        $ref = BaremetalRef::normalize($baremetalId);
        return $this->client->delete("/baremetal/{$ref}/reinstall");
    }

    /**
     * Enable monitoring for a baremetal server.
     *
     * @param string $baremetalId Server uuid
     * @return array
     */
    public function monitoringEnable(string $baremetalId): array
    {
        $ref = BaremetalRef::normalize($baremetalId);
        return $this->client->put("/baremetal/{$ref}/monitoring?enable=true");
    }

    /**
     * Disable monitoring for a baremetal server.
     *
     * @param string $baremetalId Server uuid
     * @return array
     */
    public function monitoringDisable(string $baremetalId): array
    {
        $ref = BaremetalRef::normalize($baremetalId);
        return $this->client->put("/baremetal/{$ref}/monitoring?enable=false");
    }
}
