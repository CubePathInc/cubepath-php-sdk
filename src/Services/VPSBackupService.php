<?php

namespace Cubepath\Services;

use Cubepath\CubepathClient;

class VPSBackupService
{
    private CubepathClient $client;

    public function __construct(CubepathClient $client)
    {
        $this->client = $client;
    }

    /**
     * List all backups for a VPS.
     *
     * @param int $vpsId
     * @return array Array of VPSBackup objects (from "backups" key)
     */
    public function list(int $vpsId): array
    {
        return $this->client->get("/vps/{$vpsId}/backups");
    }

    /**
     * Create a manual backup.
     *
     * @param int         $vpsId
     * @param string|null $notes Optional backup notes
     * @return array
     */
    public function create(int $vpsId, ?string $notes = null): array
    {
        $params = [];
        if ($notes !== null && $notes !== '') {
            $params['notes'] = $notes;
        }
        return $this->client->post("/vps/{$vpsId}/backups", $params);
    }

    /**
     * Restore VPS from a backup.
     *
     * @param int $vpsId
     * @param int $backupId
     * @return array
     */
    public function restore(int $vpsId, int $backupId): array
    {
        return $this->client->post("/vps/{$vpsId}/backups/{$backupId}/restore", [
            'confirm' => true,
        ]);
    }

    /**
     * Delete a backup.
     *
     * @param int $vpsId
     * @param int $backupId
     * @return array
     */
    public function delete(int $vpsId, int $backupId): array
    {
        return $this->client->delete("/vps/{$vpsId}/backups/{$backupId}");
    }

    /**
     * Get backup settings for a VPS.
     *
     * @param int $vpsId
     * @return array Contains enabled, schedule_hour, retention_days, max_backups
     */
    public function getSettings(int $vpsId): array
    {
        return $this->client->get("/vps/{$vpsId}/backup/settings");
    }

    /**
     * Update backup settings.
     *
     * @param int   $vpsId
     * @param array $params {
     *     @type bool $enabled        Enable/disable auto backups
     *     @type int  $schedule_hour  Hour of day (0-23)
     *     @type int  $retention_days Days to keep (1-7)
     *     @type int  $max_backups    Max backups to keep (1-10)
     * }
     * @return array
     */
    public function updateSettings(int $vpsId, array $params): array
    {
        return $this->client->put("/vps/{$vpsId}/backup/settings", $params);
    }
}
