<?php

namespace Cubepath\Services;

use Cubepath\CubepathClient;

class LoadBalancerService
{
    private CubepathClient $client;

    public function __construct(CubepathClient $client)
    {
        $this->client = $client;
    }

    // --- Load Balancers ---

    public function list(): array
    {
        return $this->client->get('/loadbalancer/');
    }

    public function get(string $lbUUID): array
    {
        return $this->client->get("/loadbalancer/{$lbUUID}");
    }

    /**
     * Create a new load balancer.
     *
     * @param array $params {
     *     @type string $name           Load balancer name (required)
     *     @type string $plan_name      Plan (required, e.g., "lb.small")
     *     @type string $location_name  Location (required, e.g., "us-mia-1")
     *     @type string $label          Display label (optional)
     *     @type int    $project_id     Project ID (optional)
     *     @type int    $network_id     Private network ID — attach the LB to a private network (optional)
     * }
     * @return array Contains uuid, detail
     */
    public function create(array $params): array
    {
        return $this->client->post('/loadbalancer/', $params);
    }

    public function update(string $lbUUID, array $params): array
    {
        return $this->client->patch("/loadbalancer/{$lbUUID}", $params);
    }

    public function delete(string $lbUUID): array
    {
        return $this->client->delete("/loadbalancer/{$lbUUID}");
    }

    public function resize(string $lbUUID, string $planName): array
    {
        return $this->client->post("/loadbalancer/{$lbUUID}/resize", [
            'plan_name' => $planName,
        ]);
    }

    public function listPlans(): array
    {
        return $this->client->get('/loadbalancer/plans');
    }

    // --- Listeners ---

    public function createListener(string $lbUUID, array $params): array
    {
        return $this->client->post("/loadbalancer/{$lbUUID}/listeners", $params);
    }

    public function updateListener(string $lbUUID, string $listenerUUID, array $params): array
    {
        return $this->client->patch("/loadbalancer/{$lbUUID}/listeners/{$listenerUUID}", $params);
    }

    public function deleteListener(string $lbUUID, string $listenerUUID): array
    {
        return $this->client->delete("/loadbalancer/{$lbUUID}/listeners/{$listenerUUID}");
    }

    // --- Targets ---

    public function addTarget(string $lbUUID, string $listenerUUID, array $params): array
    {
        return $this->client->post("/loadbalancer/{$lbUUID}/listeners/{$listenerUUID}/targets", $params);
    }

    public function updateTarget(string $lbUUID, string $listenerUUID, string $targetUUID, array $params): array
    {
        return $this->client->patch("/loadbalancer/{$lbUUID}/listeners/{$listenerUUID}/targets/{$targetUUID}", $params);
    }

    public function removeTarget(string $lbUUID, string $listenerUUID, string $targetUUID): array
    {
        return $this->client->delete("/loadbalancer/{$lbUUID}/listeners/{$listenerUUID}/targets/{$targetUUID}");
    }

    public function drainTarget(string $lbUUID, string $listenerUUID, string $targetUUID): array
    {
        return $this->client->post("/loadbalancer/{$lbUUID}/listeners/{$listenerUUID}/targets/{$targetUUID}/drain");
    }

    // --- Health Checks ---

    public function configureHealthCheck(string $lbUUID, string $listenerUUID, array $params): array
    {
        return $this->client->put("/loadbalancer/{$lbUUID}/listeners/{$listenerUUID}/health-check", $params);
    }

    public function deleteHealthCheck(string $lbUUID, string $listenerUUID): array
    {
        return $this->client->delete("/loadbalancer/{$lbUUID}/listeners/{$listenerUUID}/health-check");
    }
}
