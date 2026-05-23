<?php

namespace Cubepath\Services;

use Cubepath\CubepathClient;

class CDNService
{
    private CubepathClient $client;

    public function __construct(CubepathClient $client)
    {
        $this->client = $client;
    }

    // --- Zones ---

    public function listZones(): array
    {
        return $this->client->get('/cdn/zones');
    }

    public function getZone(string $zoneUUID): array
    {
        return $this->client->get("/cdn/zones/{$zoneUUID}");
    }

    public function createZone(array $params): array
    {
        return $this->client->post('/cdn/zones', $params);
    }

    public function updateZone(string $zoneUUID, array $params): array
    {
        return $this->client->patch("/cdn/zones/{$zoneUUID}", $params);
    }

    public function deleteZone(string $zoneUUID): array
    {
        return $this->client->delete("/cdn/zones/{$zoneUUID}");
    }

    /**
     * Get zone pricing (returns raw JSON).
     */
    public function getZonePricing(string $zoneUUID): string
    {
        return $this->client->getRaw("/cdn/zones/{$zoneUUID}/pricing");
    }

    public function listPlans(): array
    {
        return $this->client->get('/cdn/plans');
    }

    // --- Origins ---

    public function listOrigins(string $zoneUUID): array
    {
        return $this->client->get("/cdn/zones/{$zoneUUID}/origins");
    }

    public function createOrigin(string $zoneUUID, array $params): array
    {
        return $this->client->post("/cdn/zones/{$zoneUUID}/origins", $params);
    }

    public function updateOrigin(string $zoneUUID, string $originUUID, array $params): array
    {
        return $this->client->patch("/cdn/zones/{$zoneUUID}/origins/{$originUUID}", $params);
    }

    public function deleteOrigin(string $zoneUUID, string $originUUID): array
    {
        return $this->client->delete("/cdn/zones/{$zoneUUID}/origins/{$originUUID}");
    }

    // --- Rules ---

    public function listRules(string $zoneUUID): array
    {
        return $this->client->get("/cdn/zones/{$zoneUUID}/rules");
    }

    public function getRule(string $zoneUUID, string $ruleUUID): array
    {
        return $this->client->get("/cdn/zones/{$zoneUUID}/rules/{$ruleUUID}");
    }

    public function createRule(string $zoneUUID, array $params): array
    {
        return $this->client->post("/cdn/zones/{$zoneUUID}/rules", $params);
    }

    public function updateRule(string $zoneUUID, string $ruleUUID, array $params): array
    {
        return $this->client->patch("/cdn/zones/{$zoneUUID}/rules/{$ruleUUID}", $params);
    }

    public function deleteRule(string $zoneUUID, string $ruleUUID): array
    {
        return $this->client->delete("/cdn/zones/{$zoneUUID}/rules/{$ruleUUID}");
    }

    // --- WAF Rules ---

    public function listWAFRules(string $zoneUUID): array
    {
        return $this->client->get("/cdn/zones/{$zoneUUID}/waf-rules");
    }

    public function getWAFRule(string $zoneUUID, string $ruleUUID): array
    {
        return $this->client->get("/cdn/zones/{$zoneUUID}/waf-rules/{$ruleUUID}");
    }

    public function createWAFRule(string $zoneUUID, array $params): array
    {
        return $this->client->post("/cdn/zones/{$zoneUUID}/waf-rules", $params);
    }

    public function updateWAFRule(string $zoneUUID, string $ruleUUID, array $params): array
    {
        return $this->client->patch("/cdn/zones/{$zoneUUID}/waf-rules/{$ruleUUID}", $params);
    }

    public function deleteWAFRule(string $zoneUUID, string $ruleUUID): array
    {
        return $this->client->delete("/cdn/zones/{$zoneUUID}/waf-rules/{$ruleUUID}");
    }

    // --- Metrics ---

    /**
     * Get CDN metrics (returns raw JSON).
     *
     * @param string     $zoneUUID
     * @param string     $metricType
     * @param array|null $params {
     *     @type int    $minutes
     *     @type int    $interval_seconds
     *     @type string $group_by
     *     @type int    $limit
     * }
     * @return string Raw JSON response
     */
    public function getMetrics(string $zoneUUID, string $metricType, ?array $params = null): string
    {
        $path = "/cdn/zones/{$zoneUUID}/metrics/{$metricType}";

        $query = [];
        if ($params !== null) {
            if (!empty($params['minutes'])) {
                $query[] = "minutes={$params['minutes']}";
            }
            if (!empty($params['interval_seconds'])) {
                $query[] = "interval_seconds={$params['interval_seconds']}";
            }
            if (!empty($params['group_by'])) {
                $query[] = "group_by={$params['group_by']}";
            }
            if (!empty($params['limit'])) {
                $query[] = "limit={$params['limit']}";
            }
        }
        if (!empty($query)) {
            $path .= '?' . implode('&', $query);
        }

        return $this->client->getRaw($path);
    }

    /**
     * Re-trigger automatic SSL issuance for the zone's current custom_domain.
     * Use after fixing a missing/incorrect CNAME — the PATCH zone flow only
     * queues a cert task when custom_domain changes, so this is the way to
     * retry without resetting the field.
     */
    public function requestSsl(string $zoneUUID): array
    {
        return $this->client->post("/cdn/zones/{$zoneUUID}/request-ssl");
    }

    /**
     * Reassign a CDN zone to a different project in the same organization.
     */
    public function moveZoneToProject(string $zoneUUID, int $projectId): array
    {
        return $this->client->post("/cdn/zones/{$zoneUUID}/move-project", [
            'project_id' => $projectId,
        ]);
    }
}
