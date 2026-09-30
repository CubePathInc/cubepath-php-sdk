<?php

namespace Cubepath\Services;

use Cubepath\CubepathClient;

class DDoSService
{
    private CubepathClient $client;

    public function __construct(CubepathClient $client)
    {
        $this->client = $client;
    }

    /**
     * List DDoS attack events.
     *
     * @return array Array of DDoSAttack objects, or {detail} when there are no recent attacks
     */
    public function listAttacks(): array
    {
        return $this->client->get('/ddos-attacks/attacks');
    }

    /**
     * Breakdown of one attack (vectors, sources, ports...).
     *
     * @param int $attackId
     * @return array
     */
    public function getAttackDetails(int $attackId): array
    {
        return $this->client->get("/ddos-attacks/attacks/{$attackId}/details");
    }

    /**
     * Traffic time series of one attack.
     *
     * @param int $attackId
     * @return array
     */
    public function getAttackTrafficGraph(int $attackId): array
    {
        return $this->client->get("/ddos-attacks/attacks/{$attackId}/traffic-graph");
    }
}
