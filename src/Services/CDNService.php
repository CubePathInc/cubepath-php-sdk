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

    /**
     * Create an origin. To serve a CubePath Object Storage bucket, pass object_storage_bucket_uuid
     * with only name, weight, priority and is_backup (see createBucketOrigin()).
     */
    public function createOrigin(string $zoneUUID, array $params): array
    {
        return $this->client->post("/cdn/zones/{$zoneUUID}/origins", $params);
    }

    /**
     * Serve a CubePath Object Storage bucket through this zone. The API fills the address, TLS,
     * health check and read only credentials of the bucket. Deleting the origin stops serving it.
     *
     * @param string $zoneUUID
     * @param string $bucketUUID
     * @param string $name
     * @param array  $options {
     *     @type int  $weight    (optional)
     *     @type int  $priority  (optional)
     *     @type bool $is_backup (optional)
     * }
     * @return array Contains uuid, name, address, object_storage_bucket_uuid, detail
     */
    public function createBucketOrigin(string $zoneUUID, string $bucketUUID, string $name, array $options = []): array
    {
        $allowed = array_intersect_key($options, array_flip(['weight', 'priority', 'is_backup']));
        return $this->createOrigin($zoneUUID, array_merge($allowed, [
            'name' => $name,
            'object_storage_bucket_uuid' => $bucketUUID,
        ]));
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
     * @param string     $metricType One of summary, requests, bandwidth, cache, status-codes, top-urls,
     *                               top-countries, top-asn, top-user-agents, blocked, pops, file-extensions
     * @param array|null $params {
     *     @type int    $minutes          Window in minutes (default 60)
     *     @type int    $interval_seconds Bucket size of time series (requests, bandwidth, cache)
     *     @type string $group_by         "time" or "region" (bandwidth)
     *     @type int    $limit            Rows of the top-* and file-extensions lists
     *     @type string $country          Filter by country code (optional)
     *     @type string $asn              Filter by ASN (optional)
     *     @type string $status_range     Filter by status class, e.g. "5xx" (optional)
     *     @type string $status           Filter by status code (optional)
     *     @type string $cache_status     Filter by cache status, e.g. "HIT" (optional)
     *     @type string $device_type      Filter by device type (optional)
     *     @type string $path_prefix      Filter by path prefix (optional)
     * }
     * @return string Raw JSON response
     */
    public function getMetrics(string $zoneUUID, string $metricType, ?array $params = null): string
    {
        $path = "/cdn/zones/{$zoneUUID}/metrics/{$metricType}";

        $query = [];
        $keys = [
            'minutes', 'interval_seconds', 'group_by', 'limit', 'country', 'asn',
            'status_range', 'status', 'cache_status', 'device_type', 'path_prefix',
        ];
        foreach ($keys as $key) {
            if ($params !== null && !empty($params[$key])) {
                $query[$key] = $params[$key];
            }
        }
        if (!empty($query)) {
            $path .= '?' . http_build_query($query);
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

    // --- Cache purge ---

    /**
     * Purge cached files of the zone, on the system hostname and the custom domain.
     *
     * @param string $zoneUUID
     * @param array  $params {
     *     @type string[] $paths      Up to 100 paths starting with "/" (optional)
     *     @type bool     $everything Purge every cached file instead (optional)
     * }
     * @return array Contains detail, purge_uuid, status
     */
    public function purgeCache(string $zoneUUID, array $params): array
    {
        return $this->client->post("/cdn/zones/{$zoneUUID}/purge-cache", $params);
    }

    /**
     * List the recent cache purges of the zone with their progress per PoP.
     *
     * @param string $zoneUUID
     * @return array List of purges (purge_uuid, scope, paths, status, requested_at, completed_at,
     *               nodes, pops)
     */
    public function listPurges(string $zoneUUID): array
    {
        return $this->client->get("/cdn/zones/{$zoneUUID}/purge-cache");
    }

    // --- Token authentication ---

    /**
     * Generate a new token authentication secret. URLs signed with the old one stop working.
     *
     * @param string $zoneUUID
     * @return array Contains detail, token_auth_secret
     */
    public function rotateTokenSecret(string $zoneUUID): array
    {
        return $this->client->post("/cdn/zones/{$zoneUUID}/token-auth/rotate-secret");
    }

    /**
     * Sign a URL of a zone with token authentication enabled.
     *
     * @param string      $zoneUUID
     * @param string      $path      Path starting with "/"
     * @param int         $expiresIn Validity in seconds, 60 to 604800 (default 3600)
     * @param string|null $clientIp  Required when the zone binds tokens to the client IP
     * @return array Contains detail, signed_url, token, expires
     */
    public function signURL(string $zoneUUID, string $path, int $expiresIn = 3600, ?string $clientIp = null): array
    {
        $body = ['path' => $path, 'expires_in' => $expiresIn];
        if ($clientIp !== null) {
            $body['client_ip'] = $clientIp;
        }
        return $this->client->post("/cdn/zones/{$zoneUUID}/token-auth/sign-url", $body);
    }
}
