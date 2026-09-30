<?php

namespace Cubepath\Services;

use Cubepath\CubepathClient;

/**
 * Managed Databases: MySQL, PostgreSQL and Valkey clusters run by CubePath.
 *
 * Creating, scaling, reconfiguring, rotating credentials and deleting are asynchronous: the call
 * returns at once and the instance moves through provisioning, scaling or updating before it is
 * "active" again (poll get()). Logical databases and users start as "pending".
 */
class ManagedDatabaseService
{
    private CubepathClient $client;

    public function __construct(CubepathClient $client)
    {
        $this->client = $client;
    }

    // --- Plans ---

    /**
     * List the plans grouped by location. cpu, memory_gb and storage_gb are per node and
     * price_per_hour is per node per hour.
     *
     * @param string|null $engine Only plans of this engine: "mysql", "postgresql" or "valkey" (optional)
     * @return array List of locations (location_name, location_description, plans[] of uuid, name,
     *               description, engine, cpu, memory_gb, storage_gb, max_replicas, price_per_hour)
     */
    public function listPlans(?string $engine = null): array
    {
        $query = $engine !== null && $engine !== '' ? ['engine' => $engine] : [];
        return $this->client->get('/managed-database-plans/', $query);
    }

    // --- Instances ---

    /**
     * List the organization's managed databases.
     *
     * @return array List of instances (uuid, project_id, name, label, engine, version, status,
     *               endpoint_host, endpoint_port, replicas, protected)
     */
    public function list(): array
    {
        return $this->client->get('/managed-databases/');
    }

    /**
     * Get a managed database.
     *
     * @param string $uuid
     * @return array The list fields plus topology, plan, location, backup_enabled,
     *               backup_schedule_cron, backup_retention_days, billing_type, updated_at
     */
    public function get(string $uuid): array
    {
        return $this->client->get("/managed-databases/{$uuid}");
    }

    /**
     * Create a managed database. The plan decides the location. The instance starts as
     * "provisioning" and is billed once it becomes "active".
     *
     * @param array $params {
     *     @type int    $project_id Project ID (required)
     *     @type string $name       Lowercase letters, numbers and hyphens, 2-60 chars (required)
     *     @type string $engine     "mysql", "postgresql" or "valkey" (required)
     *     @type string $version    Engine version, e.g. "8.0.39", "17.5.0" or "7.2.11" (required)
     *     @type string $plan_uuid  Plan UUID from listPlans() (required)
     *     @type int    $replicas   Number of nodes (optional, default 3; at least 3 for mysql and 2 otherwise)
     *     @type string $topology   Cluster topology (optional, engine default)
     *     @type array  $backup     {schedule_cron, retention_days} (optional)
     * }
     * @return array Contains detail, uuid, name, engine, version, status
     */
    public function create(array $params): array
    {
        return $this->client->post('/managed-databases/', $params);
    }

    /**
     * Update the name, label or backup policy of a managed database.
     *
     * @param string $uuid
     * @param array  $params {
     *     @type string $name   New name (optional)
     *     @type string $label  New label (optional)
     *     @type array  $backup {enabled, schedule_cron, retention_days} (optional)
     * }
     * @return array Contains detail
     */
    public function update(string $uuid, array $params): array
    {
        return $this->client->patch("/managed-databases/{$uuid}", $params);
    }

    /**
     * Delete a managed database and all its data. Not allowed while it is protected or still
     * provisioning (409).
     *
     * @param string $uuid
     * @return array Contains detail
     */
    public function delete(string $uuid): array
    {
        return $this->client->delete("/managed-databases/{$uuid}");
    }

    /**
     * Enable or disable delete protection.
     *
     * @param string $uuid
     * @param bool   $enabled
     * @return array Contains detail
     */
    public function protection(string $uuid, bool $enabled): array
    {
        return $this->client->post("/managed-databases/{$uuid}/protection", [
            'enabled' => $enabled,
        ]);
    }

    /**
     * Scale a managed database: change the number of nodes or move to another plan of the same
     * engine and location. Pass exactly one of the two keys.
     *
     * @param string $uuid
     * @param array  $params {
     *     @type int    $replicas  New number of nodes (optional)
     *     @type string $plan_uuid New plan UUID (optional)
     * }
     * @return array Contains detail, uuid and replicas or plan
     */
    public function scale(string $uuid, array $params): array
    {
        return $this->client->post("/managed-databases/{$uuid}/scale", $params);
    }

    // --- Credentials ---

    /**
     * Get the connection details of the admin user. Available once the instance is active.
     *
     * @param string $uuid
     * @return array Contains host, port, username, password, uri
     */
    public function getCredentials(string $uuid): array
    {
        return $this->client->get("/managed-databases/{$uuid}/credentials");
    }

    /**
     * Generate a new admin password. The new password is not returned: read it with
     * getCredentials() once the instance is active again.
     *
     * @param string $uuid
     * @return array Contains detail, uuid
     */
    public function rotateCredentials(string $uuid): array
    {
        return $this->client->post("/managed-databases/{$uuid}/credentials/rotate");
    }

    // --- Configuration ---

    /**
     * Get the tunable parameters of the engine with their type, range and default.
     *
     * @param string $uuid
     * @return array Contains engine, params (name => {type, default, requires_restart, description,
     *               value, value_source, min, max, allowed}), note
     */
    public function getConfig(string $uuid): array
    {
        return $this->client->get("/managed-databases/{$uuid}/config");
    }

    /**
     * Change engine parameters. Some parameters need a rolling restart, listed in
     * requires_restart.
     *
     * @param string $uuid
     * @param array  $params Parameter name => new value, e.g. ['max_connections' => 500]
     * @return array Contains detail, uuid, requires_restart
     */
    public function updateConfig(string $uuid, array $params): array
    {
        return $this->client->patch("/managed-databases/{$uuid}/config", [
            'params' => $params,
        ]);
    }

    // --- Metrics ---

    /**
     * Get time series of the instance.
     *
     * @param string   $uuid
     * @param string[] $metrics   Subset of connections, cpu, memory, replication_lag (optional, default all)
     * @param string   $timeRange "1h" (default), "24h", "7d", "30d"...
     * @return array Contains start, end, metrics (name => list of [timestamp, value])
     */
    public function getMetrics(string $uuid, array $metrics = [], string $timeRange = '1h'): array
    {
        $query = ['time_range' => $timeRange];
        if (!empty($metrics)) {
            $query['metrics'] = implode(',', $metrics);
        }
        return $this->client->get("/managed-databases/{$uuid}/metrics", $query);
    }

    // --- Logical databases ---

    /**
     * List the logical databases of an instance.
     *
     * @param string $uuid
     * @return array List of databases (uuid, name, status, created_at)
     */
    public function listDatabases(string $uuid): array
    {
        return $this->client->get("/managed-databases/{$uuid}/databases");
    }

    /**
     * Create a logical database. Not available on Valkey.
     *
     * @param string $uuid
     * @param string $name Lowercase letters, digits and underscores, starting with a letter
     * @return array Contains detail, uuid, name, status ("pending")
     */
    public function createDatabase(string $uuid, string $name): array
    {
        return $this->client->post("/managed-databases/{$uuid}/databases", [
            'name' => $name,
        ]);
    }

    /**
     * Delete a logical database and its data.
     *
     * @param string $uuid
     * @param string $databaseUUID
     * @return array Contains detail
     */
    public function deleteDatabase(string $uuid, string $databaseUUID): array
    {
        return $this->client->delete("/managed-databases/{$uuid}/databases/{$databaseUUID}");
    }

    // --- Users ---

    /**
     * List the database users of an instance. Passwords are never returned.
     *
     * @param string $uuid
     * @return array List of users (uuid, username, status, created_at)
     */
    public function listUsers(string $uuid): array
    {
        return $this->client->get("/managed-databases/{$uuid}/users");
    }

    /**
     * Create a database user. The password is only returned by this call.
     *
     * @param string      $uuid
     * @param string      $username Lowercase letters, digits and underscores, starting with a letter
     * @param string|null $password 12-64 chars (optional, generated when omitted)
     * @return array Contains detail, uuid, username, password, status ("pending")
     */
    public function createUser(string $uuid, string $username, ?string $password = null): array
    {
        $body = ['username' => $username];
        if ($password !== null) {
            $body['password'] = $password;
        }
        return $this->client->post("/managed-databases/{$uuid}/users", $body);
    }

    /**
     * Delete a database user.
     *
     * @param string $uuid
     * @param string $userUUID
     * @return array Contains detail
     */
    public function deleteUser(string $uuid, string $userUUID): array
    {
        return $this->client->delete("/managed-databases/{$uuid}/users/{$userUUID}");
    }
}
