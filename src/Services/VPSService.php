<?php

namespace Cubepath\Services;

use Cubepath\CubepathClient;

class VPSService
{
    private CubepathClient $client;
    private ?VPSBackupService $backups = null;
    private ?VPSISOService $isos = null;

    public function __construct(CubepathClient $client)
    {
        $this->client = $client;
    }

    /**
     * Create a new VPS instance.
     *
     * @param int   $projectId
     * @param array $params {
     *     @type string $name           Hostname (DNS-safe, unique in org)
     *     @type string $plan_name      Plan (e.g., "rz.nano")
     *     @type string $template_name  OS template (e.g., "ubuntu-24")
     *     @type string $location_name  Location (e.g., "us-mia-1")
     *     @type string $label          Display name (optional)
     *     @type int    $network_id     Private network ID (optional)
     *     @type array  $ssh_key_names  SSH key names (optional)
     *     @type string $user           Default user (optional)
     *     @type string $password       Root/admin password (optional, min 8 chars)
     *     @type bool   $ipv4           Add IPv4 (optional)
     *     @type bool   $enable_backups Enable auto backups (optional)
     *     @type string $custom_cloudinit  Custom cloud-init YAML (optional)
     *     @type array  $firewall_group_ids  Firewall group IDs (optional)
     *     @type string $availability_group_uuid  Availability group UUID (optional)
     * }
     * @return array Task response with task_id, message, detail
     */
    public function create(int $projectId, array $params): array
    {
        return $this->client->post("/vps/create/{$projectId}", $params);
    }

    /**
     * List all VPS instances grouped by project.
     * Uses /projects/ endpoint (same as Go SDK).
     *
     * @return array Array of project responses containing VPS instances
     */
    public function list(): array
    {
        return $this->client->get('/projects/');
    }

    /**
     * Get a single VPS instance by ID.
     * Iterates through all projects to find the VPS (same as Go SDK).
     *
     * @param int $vpsId
     * @return array|null VPS details or null if not found
     * @throws \RuntimeException if VPS not found
     */
    public function get(int $vpsId): array
    {
        $projects = $this->list();
        foreach ($projects as $project) {
            if (isset($project['vps']) && is_array($project['vps'])) {
                foreach ($project['vps'] as $vps) {
                    if (isset($vps['id']) && $vps['id'] === $vpsId) {
                        return $vps;
                    }
                }
            }
        }
        throw new \RuntimeException("VPS {$vpsId} not found");
    }

    /**
     * Permanently destroy a VPS instance.
     *
     * @param int  $vpsId
     * @param bool $releaseIPs Release associated floating IPs
     * @return array
     */
    public function destroy(int $vpsId, bool $releaseIPs = true): array
    {
        return $this->client->post("/vps/destroy/{$vpsId}", [
            'release_ips' => $releaseIPs,
        ]);
    }

    /**
     * Update VPS label or name.
     *
     * @param int   $vpsId
     * @param array $params {
     *     @type string $name   New hostname (optional)
     *     @type string $label  New display label (optional)
     * }
     * @return array
     */
    public function update(int $vpsId, array $params): array
    {
        return $this->client->patch("/vps/update/{$vpsId}", $params);
    }

    /**
     * Resize VPS to a different plan.
     *
     * @param int    $vpsId
     * @param string $planName Target plan name
     * @return array
     */
    public function resize(int $vpsId, string $planName): array
    {
        return $this->client->post("/vps/resize/vps_id/{$vpsId}/resize_plan/{$planName}");
    }

    /**
     * Change root/admin password.
     *
     * @param int    $vpsId
     * @param string $password New password (min 8 chars)
     * @return array
     */
    public function changePassword(int $vpsId, string $password): array
    {
        return $this->client->post("/vps/{$vpsId}/change-password", [
            'password' => $password,
        ]);
    }

    /**
     * Reinstall OS on VPS.
     *
     * @param int    $vpsId
     * @param string $templateName OS template name
     * @return array
     */
    public function reinstall(int $vpsId, string $templateName): array
    {
        return $this->client->post("/vps/reinstall/{$vpsId}", [
            'template_name' => $templateName,
        ]);
    }

    /**
     * Control VPS power state.
     *
     * @param int    $vpsId
     * @param string $action One of: start, stop, reboot, reset
     * @return array
     */
    public function power(int $vpsId, string $action): array
    {
        return $this->client->post("/vps/{$vpsId}/power/{$action}");
    }

    /**
     * List available OS templates.
     *
     * @return array Templates response with operating_systems and applications
     */
    public function templates(): array
    {
        return $this->client->get('/vps/templates');
    }

    /**
     * Get the VPS Backup sub-service.
     */
    public function backups(): VPSBackupService
    {
        if ($this->backups === null) {
            $this->backups = new VPSBackupService($this->client);
        }
        return $this->backups;
    }

    /**
     * Get the VPS ISO sub-service.
     */
    public function isos(): VPSISOService
    {
        if ($this->isos === null) {
            $this->isos = new VPSISOService($this->client);
        }
        return $this->isos;
    }
}
