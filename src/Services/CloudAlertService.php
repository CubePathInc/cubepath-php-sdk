<?php

namespace Cubepath\Services;

use Cubepath\CubepathClient;

/**
 * Cloud Alerts: watch a metric of a VPS, baremetal server, availability group, Object Storage
 * bucket or the Object Storage usage of the organization, and notify a channel (Slack, Discord
 * or email) when it crosses a threshold. An organization can have up to 50 alerts.
 *
 * Monthly metrics (storage_cost_month and storage_egress_gb_month) notify once per month as soon
 * as the threshold is crossed and reset on the 1st (UTC). They only accept the "gt" and "gte"
 * operators and ignore duration_seconds and cooldown_seconds.
 */
class CloudAlertService
{
    private CubepathClient $client;

    public function __construct(CubepathClient $client)
    {
        $this->client = $client;
    }

    // --- Alerts ---

    /**
     * List alerts.
     *
     * @param array $filters {
     *     @type int    $project_id (optional)
     *     @type string $status     "enabled", "disabled", "triggered" or "resolved" (optional)
     * }
     * @return array List of alerts (id, project_id, name, target_type, target_id, target_name,
     *               metric_type, operator, threshold, status, actions_count, ...). target_name is
     *               the bucket name for bucket alerts and null otherwise.
     */
    public function list(array $filters = []): array
    {
        return $this->client->get('/triggers/', $filters);
    }

    /**
     * Get an alert with its actions.
     *
     * @param string $alertId
     * @return array
     */
    public function get(string $alertId): array
    {
        return $this->client->get("/triggers/{$alertId}");
    }

    /**
     * Create an alert.
     *
     * @param array $params {
     *     @type int    $project_id       Project ID (required)
     *     @type string $name             Alert name (required)
     *     @type string $description      (optional)
     *     @type string $target_type      "vps", "baremetal", "availability_group",
     *                                    "object_storage_bucket" or "organization" (required)
     *     @type string $target_id        VPS or baremetal ID, availability group or bucket UUID, or
     *                                    the organization ID as a string (required). A bucket alert
     *                                    must be created in the bucket's project; an organization
     *                                    alert is listed under project_id and deleted with it.
     *     @type string $metric_type      (required) Servers and availability groups: "cpu", "ram",
     *                                    "disk", "network_in" or "network_out" (baremetal only the
     *                                    network ones). Buckets: "storage_size_gb" (GiB),
     *                                    "storage_egress_gb_month" (GiB this month, before the free
     *                                    tier), "storage_error_rate_5xx" or "storage_error_rate_403"
     *                                    (percent of requests over the last 5 minutes, needs at
     *                                    least 20 requests). Organization: "storage_cost_month" (USD
     *                                    billed so far this month, about an hour behind billing) or
     *                                    "storage_egress_gb_month".
     *     @type string $operator         "gt", "lt", "gte", "lte" or "eq"; monthly metrics only "gt"
     *                                    or "gte" (required)
     *     @type float  $threshold        (required) 0 to 1000000 for server metrics. Object Storage
     *                                    metrics need a value above 0 and at most 1048576 for
     *                                    storage_size_gb, 100 for the error rates and 1000000 for
     *                                    the monthly ones.
     *     @type int    $duration_seconds How long the condition must hold (optional, default 300,
     *                                    ignored by monthly metrics)
     *     @type int    $cooldown_seconds Minimum time between notifications (optional, default 600,
     *                                    ignored by monthly metrics)
     *     @type array  $actions          List of {action_type: "notify", notificator_id} (required)
     * }
     * @return array The created alert
     */
    public function create(array $params): array
    {
        return $this->client->post('/triggers/', $params);
    }

    /**
     * Update an alert; omitted keys are left unchanged. Passing actions replaces them all.
     * Set status to "disabled" or "enabled" to pause or resume it.
     *
     * @param string $alertId
     * @param array  $params Same keys as create() plus status
     * @return array The updated alert
     */
    public function update(string $alertId, array $params): array
    {
        return $this->client->put("/triggers/{$alertId}", $params);
    }

    /**
     * Delete an alert.
     *
     * @param string $alertId
     * @return array Empty
     */
    public function delete(string $alertId): array
    {
        return $this->client->delete("/triggers/{$alertId}");
    }

    /**
     * Fired and resolved events of an alert, newest first.
     *
     * @param string $alertId
     * @param int    $limit
     * @return array
     */
    public function history(string $alertId, int $limit = 50): array
    {
        return $this->client->get("/triggers/{$alertId}/history", ['limit' => $limit]);
    }

    // --- Notification channels ---

    /**
     * List notification channels.
     *
     * @return array List of channels (id, name, type, config, enabled, ...)
     */
    public function listNotificators(): array
    {
        return $this->client->get('/triggers/notificators/');
    }

    /**
     * Get a notification channel.
     *
     * @param string $notificatorId
     * @return array
     */
    public function getNotificator(string $notificatorId): array
    {
        return $this->client->get("/triggers/notificators/{$notificatorId}");
    }

    /**
     * Create a notification channel.
     *
     * @param array $params {
     *     @type string $name    (required)
     *     @type string $type    "slack", "discord" or "email" (required)
     *     @type array  $config  Slack and Discord need an HTTPS webhook_url; email needs nothing
     *     @type bool   $enabled (optional, default true)
     * }
     * @return array The created channel
     */
    public function createNotificator(array $params): array
    {
        return $this->client->post('/triggers/notificators/', $params);
    }

    /**
     * Update a notification channel.
     *
     * @param string $notificatorId
     * @param array  $params {
     *     @type string $name    (optional)
     *     @type array  $config  (optional)
     *     @type bool   $enabled (optional)
     * }
     * @return array The updated channel
     */
    public function updateNotificator(string $notificatorId, array $params): array
    {
        return $this->client->put("/triggers/notificators/{$notificatorId}", $params);
    }

    /**
     * Delete a notification channel.
     *
     * @param string $notificatorId
     * @return array Empty
     */
    public function deleteNotificator(string $notificatorId): array
    {
        return $this->client->delete("/triggers/notificators/{$notificatorId}");
    }
}
