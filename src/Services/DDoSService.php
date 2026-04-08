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
     * @return array Array of DDoSAttack objects
     */
    public function listAttacks(): array
    {
        return $this->client->get('/ddos-attacks/attacks');
    }
}
