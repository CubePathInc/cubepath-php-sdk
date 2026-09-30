<?php

namespace Cubepath\Services;

use Cubepath\CubepathClient;

class DNSService
{
    private CubepathClient $client;

    public function __construct(CubepathClient $client)
    {
        $this->client = $client;
    }

    // --- Zones ---

    /**
     * List all DNS zones.
     *
     * @return array
     */
    public function listZones(): array
    {
        return $this->client->get('/dns/zones');
    }

    /**
     * List DNS zones for a specific project.
     *
     * @param int $projectId
     * @return array
     */
    public function listZonesByProject(int $projectId): array
    {
        return $this->client->get('/dns/zones', ['project_id' => $projectId]);
    }

    /**
     * Get a DNS zone by UUID.
     *
     * @param string $zoneUUID
     * @return array
     */
    public function getZone(string $zoneUUID): array
    {
        return $this->client->get("/dns/zones/{$zoneUUID}");
    }

    /**
     * Create a new DNS zone.
     *
     * @param array $params {
     *     @type string $domain     Domain name
     *     @type int    $project_id Project ID (optional)
     * }
     * @return array Zone with status "pending_verification"
     */
    public function createZone(array $params): array
    {
        return $this->client->post('/dns/zones', $params);
    }

    /**
     * Delete a DNS zone.
     *
     * @param string $zoneUUID
     * @return array
     */
    public function deleteZone(string $zoneUUID): array
    {
        return $this->client->delete("/dns/zones/{$zoneUUID}");
    }

    /**
     * Request zone verification.
     *
     * @param string $zoneUUID
     * @return array Contains verified, message, next_check_at
     */
    public function verifyZone(string $zoneUUID): array
    {
        return $this->client->post("/dns/zones/{$zoneUUID}/verify");
    }

    /**
     * Scan zone and auto-import records.
     *
     * @param string $zoneUUID
     * @param bool   $autoImport
     * @return array Contains imported, skipped, errors[], records[]
     */
    public function scanZone(string $zoneUUID, bool $autoImport = false): array
    {
        return $this->client->post("/dns/zones/{$zoneUUID}/scan", [
            'auto_import' => $autoImport,
        ]);
    }

    /**
     * Move zone to a different project.
     *
     * @param string $zoneUUID
     * @param int    $projectId
     * @return array
     */
    public function moveZoneProject(string $zoneUUID, int $projectId): array
    {
        return $this->client->post("/dns/zones/{$zoneUUID}/move-project", [
            'project_id' => $projectId,
        ]);
    }

    // --- Records ---

    /**
     * List all records in a zone.
     *
     * @param string      $zoneUUID
     * @param string|null $recordType Filter by type (A, AAAA, CNAME, MX, etc.)
     * @return array
     */
    public function listRecords(string $zoneUUID, ?string $recordType = null): array
    {
        $query = $recordType ? ['record_type' => $recordType] : [];
        return $this->client->get("/dns/zones/{$zoneUUID}/records", $query);
    }

    /**
     * Create a DNS record.
     *
     * @param string $zoneUUID
     * @param array  $params {
     *     @type string $name     Record name (e.g., "www")
     *     @type string $type     Record type (A, AAAA, CNAME, MX, TXT, etc.)
     *     @type string $content  Record content/value
     *     @type int    $ttl      TTL in seconds
     *     @type int    $priority Priority (for MX, SRV records)
     *     @type int    $weight   Weight (for SRV records)
     *     @type int    $port     Port (for SRV records)
     *     @type string $comment  Optional comment
     * }
     * @return array
     */
    public function createRecord(string $zoneUUID, array $params): array
    {
        return $this->client->post("/dns/zones/{$zoneUUID}/records", $params);
    }

    /**
     * Update a DNS record.
     *
     * @param string $zoneUUID
     * @param string $recordUUID
     * @param array  $params Same as createRecord
     * @return array
     */
    public function updateRecord(string $zoneUUID, string $recordUUID, array $params): array
    {
        return $this->client->put("/dns/zones/{$zoneUUID}/records/{$recordUUID}", $params);
    }

    /**
     * Delete a DNS record.
     *
     * @param string $zoneUUID
     * @param string $recordUUID
     * @return array
     */
    public function deleteRecord(string $zoneUUID, string $recordUUID): array
    {
        return $this->client->delete("/dns/zones/{$zoneUUID}/records/{$recordUUID}");
    }

    // --- SOA ---

    /**
     * Get SOA record for a zone.
     *
     * @param string $zoneUUID
     * @return array Contains primary_ns, hostmaster, serial, refresh, retry, expire, minimum
     */
    public function getSOA(string $zoneUUID): array
    {
        return $this->client->get("/dns/zones/{$zoneUUID}/soa");
    }

    /**
     * Update SOA record for a zone.
     *
     * @param string $zoneUUID
     * @param array  $params {
     *     @type string $primary_ns  Primary nameserver
     *     @type string $hostmaster  Hostmaster email
     *     @type int    $refresh     Refresh interval
     *     @type int    $retry       Retry interval
     *     @type int    $expire      Expire time
     *     @type int    $minimum     Minimum TTL
     * }
     * @return array
     */
    public function updateSOA(string $zoneUUID, array $params): array
    {
        return $this->client->put("/dns/zones/{$zoneUUID}/soa", $params);
    }

    // --- GeoDNS regions and health checks ---

    /**
     * List GeoDNS regions.
     *
     * @return array List of regions (code, name)
     */
    public function listRegions(): array
    {
        return $this->client->get('/dns/regions');
    }

    /**
     * List the health checks of a zone's records.
     *
     * @param string $zoneUUID
     * @return array List of health checks
     */
    public function listHealthChecks(string $zoneUUID): array
    {
        return $this->client->get("/dns/zones/{$zoneUUID}/health-checks");
    }

    /**
     * Get the health check of a record.
     *
     * @param string $zoneUUID
     * @param string $recordUUID
     * @return array Contains uuid, record_uuid, name, check_type, target, port, path, expected_status,
     *               interval_secs, timeout_secs, healthy_threshold, unhealthy_threshold, enabled,
     *               last_status, last_check_at
     */
    public function getHealthCheck(string $zoneUUID, string $recordUUID): array
    {
        return $this->client->get("/dns/zones/{$zoneUUID}/records/{$recordUUID}/health-check");
    }

    /**
     * Create or replace the health check of a record, for automatic failover. Each check is a
     * billed add-on.
     *
     * @param string $zoneUUID
     * @param string $recordUUID
     * @param array  $params {
     *     @type string $name                Friendly name (required)
     *     @type string $check_type          "http", "https", "tcp" or "ping" (required)
     *     @type string $target              Host or IP to probe (optional, default: the record value)
     *     @type int    $port                Required for tcp
     *     @type string $path                For http and https
     *     @type int    $expected_status     (optional, default 200)
     *     @type int    $interval_secs       (optional, default 60, min 10)
     *     @type int    $timeout_secs        (optional, default 5)
     *     @type int    $healthy_threshold   (optional, default 2)
     *     @type int    $unhealthy_threshold (optional, default 3)
     *     @type bool   $enabled             (optional, default true)
     * }
     * @return array The health check
     */
    public function setHealthCheck(string $zoneUUID, string $recordUUID, array $params): array
    {
        return $this->client->put("/dns/zones/{$zoneUUID}/records/{$recordUUID}/health-check", $params);
    }

    /**
     * Delete the health check of a record.
     *
     * @param string $zoneUUID
     * @param string $recordUUID
     * @return array
     */
    public function deleteHealthCheck(string $zoneUUID, string $recordUUID): array
    {
        return $this->client->delete("/dns/zones/{$zoneUUID}/records/{$recordUUID}/health-check");
    }
}
