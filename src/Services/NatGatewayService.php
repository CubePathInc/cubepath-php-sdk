<?php

namespace Cubepath\Services;

use Cubepath\APIError;
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
     * Traffic of a NAT gateway (series bytes_in and bytes_out, bytes per second), served
     * through GraphQL.
     *
     * @param string $uuid
     * @param string $range H1 (default), H3, H6, H12, H24, D3, D7 or D30
     * @return array Contains start, end, step, series[] (name, unit, points[] of ts, value)
     * @throws APIError 404 when the gateway does not exist
     */
    public function getMetrics(string $uuid, string $range = 'H1'): array
    {
        $data = $this->client->graphql(
            'query($uuid: ID!, $range: TimeRange!) { natGateway(uuid: $uuid) { metrics(range: $range) { start end step series { name unit points { ts value } } } } }',
            ['uuid' => $uuid, 'range' => $range]
        );
        if (empty($data['natGateway'])) {
            throw new APIError(404, "NAT gateway {$uuid} not found");
        }
        return $data['natGateway']['metrics'];
    }

    /**
     * Month-to-date traffic of a NAT gateway, served through GraphQL.
     *
     * @param string $uuid
     * @return array Contains inBytes, outBytes, totalBytes, periodStart, periodEnd
     * @throws APIError 404 when the gateway does not exist
     */
    public function getBandwidthUsage(string $uuid): array
    {
        $data = $this->client->graphql(
            'query($uuid: ID!) { natGateway(uuid: $uuid) { bandwidthUsage { inBytes outBytes totalBytes periodStart periodEnd } } }',
            ['uuid' => $uuid]
        );
        if (empty($data['natGateway'])) {
            throw new APIError(404, "NAT gateway {$uuid} not found");
        }
        return $data['natGateway']['bandwidthUsage'];
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
