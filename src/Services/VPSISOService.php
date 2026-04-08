<?php

namespace Cubepath\Services;

use Cubepath\CubepathClient;

class VPSISOService
{
    private CubepathClient $client;

    public function __construct(CubepathClient $client)
    {
        $this->client = $client;
    }

    /**
     * List available ISOs for a VPS.
     *
     * @param int $vpsId
     * @return array Contains items[] and mounted_iso_id
     */
    public function list(int $vpsId): array
    {
        return $this->client->get("/vps/{$vpsId}/isos");
    }

    /**
     * Mount an ISO to a VPS.
     *
     * @param int    $vpsId
     * @param string $isoId
     * @return array
     */
    public function mount(int $vpsId, string $isoId): array
    {
        return $this->client->post("/vps/{$vpsId}/iso", [
            'iso_id' => $isoId,
        ]);
    }

    /**
     * Unmount the current ISO from a VPS.
     *
     * @param int $vpsId
     * @return array
     */
    public function unmount(int $vpsId): array
    {
        return $this->client->delete("/vps/{$vpsId}/iso");
    }
}
