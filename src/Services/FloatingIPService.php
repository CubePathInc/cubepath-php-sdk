<?php

namespace Cubepath\Services;

use Cubepath\BaremetalRef;
use Cubepath\CubepathClient;

class FloatingIPService
{
    private CubepathClient $client;

    public function __construct(CubepathClient $client)
    {
        $this->client = $client;
    }

    /**
     * List all floating IPs for the organization.
     *
     * @return array Contains single_ips[] and subnets[]
     */
    public function list(): array
    {
        return $this->client->get('/floating_ips/organization');
    }

    /**
     * Acquire a new floating IP.
     *
     * @param string $ipType       IP type (e.g., "ipv4", "ipv6")
     * @param string $locationName Location name
     * @return array Floating IP details
     */
    public function acquire(string $ipType, string $locationName): array
    {
        return $this->client->post(
            "/floating_ips/acquire?ip_type={$ipType}&location_name={$locationName}"
        );
    }

    /**
     * Release a floating IP.
     *
     * @param string $address IP address to release
     * @return array
     */
    public function release(string $address): array
    {
        return $this->client->post("/floating_ips/release/{$address}");
    }

    /**
     * Assign a floating IP to a resource (VPS or baremetal).
     *
     * @param string $resourceType "vps" or "baremetal"
     * @param int|string $resourceId Resource ID. For "baremetal" the server number or uuid; for "vps" the VPS ID
     * @param string $address      IP address to assign
     * @return array
     */
    public function assign(string $resourceType, $resourceId, string $address): array
    {
        if ($resourceType === 'baremetal') {
            $resourceId = BaremetalRef::normalize($resourceId);
        } elseif (is_string($resourceId) && preg_match('/\A[0-9]+\z/', $resourceId) === 1) {
            $resourceId = (int) $resourceId;
        } elseif (!is_int($resourceId)) {
            throw new \InvalidArgumentException('Resource ID must be an integer for ' . $resourceType);
        }
        return $this->client->post(
            "/floating_ips/assign/{$resourceType}/{$resourceId}?address={$address}"
        );
    }

    /**
     * Unassign a floating IP from its current resource.
     *
     * @param string $address IP address
     * @return array
     */
    public function unassign(string $address): array
    {
        return $this->client->post("/floating_ips/unassign/{$address}");
    }

    /**
     * Configure reverse DNS for a floating IP.
     *
     * @param string $ip         IP address
     * @param string $reverseDNS Reverse DNS hostname
     * @return array
     */
    public function configureReverseDNS(string $ip, string $reverseDNS): array
    {
        return $this->client->post(
            "/floating_ips/reverse_dns/configure?ip={$ip}&reverse_dns={$reverseDNS}"
        );
    }
}
