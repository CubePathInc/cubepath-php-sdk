<?php

namespace Cubepath\Services;

use Cubepath\CubepathClient;

/**
 * Cloud Alerts: watch a metric of a VPS, baremetal server or availability group and notify a
 * channel (Slack, Discord or email) when it crosses a threshold.
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
     * @return array List of alerts (id, project_id, name, target_type, target_id, metric_type,
     *               operator, threshold, status, actions_count, ...)
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
     *     @type string $target_type      "vps", "baremetal" or "availability_group" (required)
     *     @type string $target_id        VPS or baremetal ID, or availability group UUID (required)
     *     @type string $metric_type      "cpu", "ram", "disk", "network_in" or "network_out" (required)
     *     @type string $operator         "gt", "lt", "gte", "lte" or "eq" (required)
     *     @type float  $threshold        (required)
     *     @type int    $duration_seconds How long the condition must hold (optional, default 300)
     *     @type int    $cooldown_seconds Minimum time between notifications (optional, default 600)
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
