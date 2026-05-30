<?php

namespace Cubepath\Services;

use Cubepath\CubepathClient;

class NatGatewayService
{
    private CubepathClient $client;

    public function __construct(CubepathClient $client)
    {
        $this->client = $client;
    }

    /**
     * List all NAT gateways.
     *
     * @return array
     */
    public function list(): array
    {
        return $this->client->get('/nat-gateway/');
    }

    /**
     * Get a single NAT gateway by UUID.
     *
     * @param string $uuid
     * @return array
     */
    public function get(string $uuid): array
    {
        return $this->client->get("/nat-gateway/{$uuid}");
    }

    /**
     * Create a new NAT gateway.
     *
     * @param array $params {
     *     @type string $name        Gateway name (required)
     *     @type string $plan_name   Plan (required, e.g., "nat.small")
     *     @type int    $network_id  Private network ID (required)
     *     @type string $label       Display label (optional)
     *     @type int    $project_id  Project ID (optional)
     * }
     * @return array Contains uuid, detail
     */
    public function create(array $params): array
    {
        return $this->client->post('/nat-gateway/', $params);
    }

    /**
     * Update a NAT gateway.
     *
     * @param string $uuid
     * @param array  $params {
     *     @type string $name  New name (optional)
     *     @type string $label New label (optional)
     * }
     * @return array
     */
    public function update(string $uuid, array $params): array
    {
        return $this->client->patch("/nat-gateway/{$uuid}", $params);
    }

    /**
     * Delete a NAT gateway.
     *
     * @param string $uuid
     * @return array
     */
    public function delete(string $uuid): array
    {
        return $this->client->delete("/nat-gateway/{$uuid}");
    }

    /**
     * Resize a NAT gateway to a new plan.
     *
     * @param string $uuid
     * @param string $planName  Target plan name (e.g., "nat.medium")
     * @return array Contains detail
     */
    public function resize(string $uuid, string $planName): array
    {
        return $this->client->post("/nat-gateway/{$uuid}/resize", [
            'plan_name' => $planName,
        ]);
    }

    /**
     * Move a NAT gateway to a different project.
     *
     * @param string $uuid
     * @param int    $projectId  Destination project ID
     * @return array Contains detail
     */
    public function moveToProject(string $uuid, int $projectId): array
    {
        return $this->client->post("/nat-gateway/{$uuid}/move-to-project", [
            'project_id' => $projectId,
        ]);
    }

    /**
     * Enable or disable delete protection on a NAT gateway.
     *
     * @param string $uuid
     * @param bool   $enabled  True to enable protection, false to disable
     * @return array Contains detail
     */
    public function protection(string $uuid, bool $enabled): array
    {
        return $this->client->post("/nat-gateway/{$uuid}/protection", [
            'enabled' => $enabled,
        ]);
    }

    /**
     * Get metrics for a NAT gateway.
     *
     * @param string $uuid
     * @return array
     */
    public function getMetrics(string $uuid): array
    {
        return $this->client->get("/nat-gateway/{$uuid}/metrics");
    }

    /**
     * Get bandwidth usage for a NAT gateway.
     *
     * @param string $uuid
     * @return array
     */
    public function getBandwidthUsage(string $uuid): array
    {
        return $this->client->get("/nat-gateway/{$uuid}/bandwidth-usage");
    }

    /**
     * List available NAT gateway plans.
     *
     * @return array
     */
    public function listPlans(): array
    {
        return $this->client->get('/nat-gateway/plans');
    }
}
