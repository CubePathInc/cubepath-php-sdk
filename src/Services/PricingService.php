<?php

namespace Cubepath\Services;

use Cubepath\CubepathClient;

class PricingService
{
    private CubepathClient $client;

    public function __construct(CubepathClient $client)
    {
        $this->client = $client;
    }

    /**
     * Get pricing information for all services.
     *
     * @return array
     */
    public function get(): array
    {
        return $this->client->get('/pricing');
    }
}
