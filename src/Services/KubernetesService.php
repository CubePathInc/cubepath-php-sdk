<?php

namespace Cubepath\Services;

use Cubepath\CubepathClient;

class KubernetesService
{
    private CubepathClient $client;

    public function __construct(CubepathClient $client)
    {
        $this->client = $client;
    }

    // --- Cluster management ---

    public function listVersions(): array
    {
        return $this->client->get('/kubernetes/versions');
    }

    public function listPlans(string $version = ''): array
    {
        $path = '/kubernetes/plans';
        if ($version !== '') {
            $path .= "?version={$version}";
        }
        return $this->client->get($path);
    }

    public function list(): array
    {
        return $this->client->get('/kubernetes/');
    }

    public function get(string $clusterUUID): array
    {
        return $this->client->get("/kubernetes/{$clusterUUID}");
    }

    /**
     * Create a Kubernetes cluster.
     *
     * @param array $params {
     *     @type int    $project_id       Project ID
     *     @type string $name             Cluster name
     *     @type string $location_name    Location
     *     @type string $version          K8s version (optional)
     *     @type bool   $ha_control_plane HA control plane
     *     @type array  $node_pools       Array of {name, plan, count}
     *     @type array  $network          Network config (optional)
     * }
     * @return array Contains detail, uuid
     */
    public function create(array $params): array
    {
        return $this->client->post('/kubernetes/', $params);
    }

    public function update(string $clusterUUID, array $params): array
    {
        return $this->client->patch("/kubernetes/{$clusterUUID}", $params);
    }

    public function delete(string $clusterUUID): array
    {
        return $this->client->delete("/kubernetes/{$clusterUUID}");
    }

    /**
     * Get kubeconfig for a cluster.
     *
     * @param string $clusterUUID
     * @return string Kubeconfig YAML content
     */
    public function getKubeconfig(string $clusterUUID): string
    {
        $raw = $this->client->getRaw("/kubernetes/{$clusterUUID}/kubeconfig");
        $data = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($data['kubeconfig'])) {
            return $data['kubeconfig'];
        }
        return $raw;
    }

    public function move(string $clusterUUID, int $projectId): array
    {
        return $this->client->post("/kubernetes/{$clusterUUID}/move", [
            'project_id' => $projectId,
        ]);
    }

    public function listLoadBalancers(string $clusterUUID): array
    {
        return $this->client->get("/kubernetes/{$clusterUUID}/loadbalancers");
    }

    // --- Node Pools ---

    public function listNodePools(string $clusterUUID): array
    {
        return $this->client->get("/kubernetes/{$clusterUUID}/node-pools/");
    }

    public function createNodePool(string $clusterUUID, array $params): array
    {
        return $this->client->post("/kubernetes/{$clusterUUID}/node-pools/", $params);
    }

    public function updateNodePool(string $clusterUUID, string $poolUUID, array $params): array
    {
        return $this->client->patch("/kubernetes/{$clusterUUID}/node-pools/{$poolUUID}", $params);
    }

    public function deleteNodePool(string $clusterUUID, string $poolUUID): array
    {
        return $this->client->delete("/kubernetes/{$clusterUUID}/node-pools/{$poolUUID}");
    }

    public function addNodes(string $clusterUUID, string $poolUUID, int $count): array
    {
        return $this->client->post("/kubernetes/{$clusterUUID}/node-pools/{$poolUUID}/nodes", [
            'count' => $count,
        ]);
    }

    public function removeNode(string $clusterUUID, string $poolUUID, string $vpsId): array
    {
        return $this->client->delete("/kubernetes/{$clusterUUID}/node-pools/{$poolUUID}/nodes/{$vpsId}");
    }

    // --- Addons ---

    public function listAvailableAddons(): array
    {
        return $this->client->get('/kubernetes/addons');
    }

    public function getAddon(string $slug): array
    {
        return $this->client->get("/kubernetes/addons/{$slug}");
    }

    public function listInstalledAddons(string $clusterUUID): array
    {
        return $this->client->get("/kubernetes/{$clusterUUID}/addons");
    }

    public function installAddon(string $clusterUUID, string $slug, ?array $customValues = null): array
    {
        $body = [];
        if ($customValues !== null && !empty($customValues)) {
            $body['custom_values'] = $customValues;
        }
        return $this->client->post("/kubernetes/{$clusterUUID}/addons/{$slug}/install", $body);
    }

    public function uninstallAddon(string $clusterUUID, string $addonUUID): array
    {
        return $this->client->delete("/kubernetes/{$clusterUUID}/addons/{$addonUUID}");
    }
}
