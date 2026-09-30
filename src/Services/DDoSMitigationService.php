<?php

namespace Cubepath\Services;

use Cubepath\CubepathClient;

/**
 * DDoS Mitigation of your IPs: protection profiles, filtering rules, geo, ASN and prefix list
 * filtering, and packet captures.
 *
 * Profiles and traffic captures need IPs with Premium protection. A network is a single IP
 * ("203.0.113.10") or, where noted, a CIDR ("203.0.113.0/24").
 */
class DDoSMitigationService
{
    private CubepathClient $client;

    public function __construct(CubepathClient $client)
    {
        $this->client = $client;
    }

    /**
     * Encode a network for the URL path, keeping the "/" of a CIDR.
     */
    private static function network(string $network): string
    {
        return str_replace('%2F', '/', rawurlencode($network));
    }

    // --- IPs and catalogs ---

    /**
     * List your Premium protected IPs and subnets with their profile status.
     *
     * @param array $filters {
     *     @type string $ip_type     "ipv4" or "ipv6" (optional)
     *     @type string $location    Location name (optional)
     *     @type bool   $has_profile Only IPs with (true) or without (false) a profile (optional)
     * }
     * @return array Contains single_ips, subnets, total
     */
    public function listIPs(array $filters = []): array
    {
        if (isset($filters['has_profile']) && is_bool($filters['has_profile'])) {
            $filters['has_profile'] = $filters['has_profile'] ? 'true' : 'false';
        }
        return $this->client->get('/ddos-mitigation/ips', $filters);
    }

    /**
     * Countries that can be used for geo filtering.
     *
     * @return array Contains countries[] (iso_code, name, ...), total
     */
    public function listCountries(): array
    {
        return $this->client->get('/ddos-mitigation/countries');
    }

    /**
     * ASNs that can be used for ASN filtering.
     *
     * @param string|null $search Filter by number or name (optional)
     * @return array Contains asns[], total
     */
    public function listASNs(?string $search = null): array
    {
        $query = $search !== null && $search !== '' ? ['search' => $search] : [];
        return $this->client->get('/ddos-mitigation/asns', $query);
    }

    // --- Protection profiles ---

    /**
     * Get the protection profile of an IP or CIDR (the defaults when it has none).
     *
     * @param string $network
     * @return array Profile levels, thresholds and modes
     */
    public function getProfile(string $network): array
    {
        return $this->client->get('/ddos-mitigation/profiles/' . self::network($network));
    }

    /**
     * Create or replace the protection profile of an IP or CIDR (a CIDR sets every IP in it).
     * Omitted fields take their default value.
     *
     * @param string $network
     * @param array  $params Levels (0-10), *_threshold_pps / *_threshold_mbps, default_action,
     *                       country_mode / asn_mode / prefix_list_mode (0 off, 1 block list,
     *                       2 allow list), syn_flood_threshold, syn_flood_block_secs,
     *                       always_on_mitigation, symmetric_routing
     * @return array Contains detail
     */
    public function updateProfile(string $network, array $params): array
    {
        return $this->client->put('/ddos-mitigation/profiles/' . self::network($network), $params);
    }

    /**
     * Delete the protection profile of an IP or CIDR, back to the defaults.
     *
     * @param string $network
     * @return array Contains detail
     */
    public function deleteProfile(string $network): array
    {
        return $this->client->delete('/ddos-mitigation/profiles/' . self::network($network));
    }

    /**
     * Countries assigned to the profile of a single IP.
     *
     * @param string $ip
     * @return array Contains countries[], total
     */
    public function getProfileCountries(string $ip): array
    {
        return $this->client->get('/ddos-mitigation/profiles/' . self::network($ip) . '/countries');
    }

    /**
     * Replace the countries of the profile of a single IP. They apply according to the
     * profile's country_mode.
     *
     * @param string   $ip
     * @param string[] $isoCodes e.g. ["CN", "RU"]; empty clears them
     * @return array Contains detail
     */
    public function setProfileCountries(string $ip, array $isoCodes): array
    {
        return $this->client->put('/ddos-mitigation/profiles/' . self::network($ip) . '/countries', [
            'iso_codes' => array_values($isoCodes),
        ]);
    }

    /**
     * ASNs assigned to the profile of a single IP.
     *
     * @param string $ip
     * @return array Contains asns[], total
     */
    public function getProfileASNs(string $ip): array
    {
        return $this->client->get('/ddos-mitigation/profiles/' . self::network($ip) . '/asns');
    }

    /**
     * Replace the ASNs of the profile of a single IP. They apply according to asn_mode.
     *
     * @param string $ip
     * @param int[]  $asns Empty clears them
     * @return array Contains detail
     */
    public function setProfileASNs(string $ip, array $asns): array
    {
        return $this->client->put('/ddos-mitigation/profiles/' . self::network($ip) . '/asns', [
            'asns' => array_values($asns),
        ]);
    }

    /**
     * Prefix lists assigned to the profile of a single IP.
     *
     * @param string $ip
     * @return array Contains prefix_lists[], total
     */
    public function getProfilePrefixLists(string $ip): array
    {
        return $this->client->get('/ddos-mitigation/profiles/' . self::network($ip) . '/prefix-lists');
    }

    /**
     * Replace the prefix lists of the profile of a single IP. They apply according to
     * prefix_list_mode.
     *
     * @param string   $ip
     * @param string[] $prefixListUUIDs Empty clears them
     * @return array Contains detail
     */
    public function setProfilePrefixLists(string $ip, array $prefixListUUIDs): array
    {
        return $this->client->put('/ddos-mitigation/profiles/' . self::network($ip) . '/prefix-lists', [
            'uuids' => array_values($prefixListUUIDs),
        ]);
    }

    // --- Filtering rules ---

    /**
     * List the filtering rules of an IP or CIDR.
     *
     * @param string $network
     * @return array Contains rules[] (id, network, protocol, dst_port, action, action_label, ...), total
     */
    public function listFirewallRules(string $network): array
    {
        return $this->client->get('/ddos-mitigation/firewall-rules/' . self::network($network));
    }

    /**
     * Create a filtering rule. A CIDR creates one rule per IP.
     *
     * @param array $params {
     *     @type string $network  IP or CIDR (required)
     *     @type int    $protocol 0 any, 1 ICMP, 6 TCP, 17 UDP (required)
     *     @type int    $dst_port Destination port, 0 for any (required)
     *     @type int    $action   0 DROP, 1 ACCEPT, 2 FILTER, or a protocol validator or
     *                            rate limit code (10-61) (required)
     *     @type int    $tcp_syn  Rate limits in packets per second (optional, also tcp_ack,
     *                            tcp_synack, tcp_rst, tcp_fin, tcp_all, udp, icmp)
     * }
     * @return array Contains detail
     */
    public function createFirewallRule(array $params): array
    {
        return $this->client->post('/ddos-mitigation/firewall-rules', $params);
    }

    /**
     * Delete a filtering rule.
     *
     * @param int $ruleId
     * @return array Contains detail
     */
    public function deleteFirewallRule(int $ruleId): array
    {
        return $this->client->delete("/ddos-mitigation/firewall-rules/{$ruleId}");
    }

    /**
     * Delete the rules with this protocol and port on every IP of a network.
     *
     * @param string $network  IP or CIDR
     * @param int    $protocol
     * @param int    $dstPort
     * @return array Contains detail
     */
    public function deleteFirewallRulesBulk(string $network, int $protocol, int $dstPort): array
    {
        return $this->client->delete('/ddos-mitigation/firewall-rules/bulk?' . http_build_query([
            'network' => $network,
            'protocol' => $protocol,
            'dst_port' => $dstPort,
        ]));
    }

    // --- Prefix lists ---

    /**
     * List your prefix lists and the global ones.
     *
     * @return array Contains prefix_lists[] (uuid, name, description, is_global, entries_count, ...), total
     */
    public function listPrefixLists(): array
    {
        return $this->client->get('/ddos-mitigation/prefix-lists');
    }

    /**
     * Create a prefix list (3 per organization).
     *
     * @param string      $name
     * @param string|null $description
     * @return array Contains detail (find the new list with listPrefixLists())
     */
    public function createPrefixList(string $name, ?string $description = null): array
    {
        $body = ['name' => $name];
        if ($description !== null) {
            $body['description'] = $description;
        }
        return $this->client->post('/ddos-mitigation/prefix-lists', $body);
    }

    /**
     * Delete one of your prefix lists.
     *
     * @param string $uuid
     * @return array Contains detail
     */
    public function deletePrefixList(string $uuid): array
    {
        return $this->client->delete("/ddos-mitigation/prefix-lists/{$uuid}");
    }

    /**
     * List the entries of a prefix list.
     *
     * @param string $uuid
     * @return array List of entries ({network})
     */
    public function listPrefixListEntries(string $uuid): array
    {
        return $this->client->get("/ddos-mitigation/prefix-lists/{$uuid}/entries");
    }

    /**
     * Add a CIDR to a prefix list (100 per list).
     *
     * @param string $uuid
     * @param string $network e.g. "198.51.100.0/24"
     * @return array Contains detail
     */
    public function addPrefixListEntry(string $uuid, string $network): array
    {
        return $this->client->post("/ddos-mitigation/prefix-lists/{$uuid}/entries", [
            'network' => $network,
        ]);
    }

    /**
     * Remove a CIDR from a prefix list.
     *
     * @param string $uuid
     * @param string $network
     * @return array Contains detail
     */
    public function deletePrefixListEntry(string $uuid, string $network): array
    {
        return $this->client->delete("/ddos-mitigation/prefix-lists/{$uuid}/entries/" . self::network($network));
    }

    // --- Traffic capture ---

    /**
     * IPs whose traffic can be captured (Premium protection).
     *
     * @return array Contains total, ips
     */
    public function listCaptureIPs(): array
    {
        return $this->client->get('/ddos-mitigation/traffic-capture/protected-ips');
    }

    /**
     * Query captured packets of one IP or subnet.
     *
     * @param array $filters {
     *     @type string $start_time     ISO 8601 (required)
     *     @type string $end_time       ISO 8601 (required)
     *     @type string $destination_ip IP or subnet (required)
     *     @type int    $limit          Max packets (optional, default 20000)
     *     ...include_/exclude_ lists of src_ips, src_ports, dst_ports, protocols, actions, tcp_flags,
     *     min_/max_ src_port, dst_port, packet_len, ttl, has_payload (optional)
     * }
     * @return array Contains start_time, end_time, total_logs, logs
     */
    public function queryTrafficCapture(array $filters): array
    {
        return $this->client->post('/ddos-mitigation/traffic-capture', $filters);
    }

    /**
     * Passed and dropped traffic over time.
     *
     * @param array $filters {
     *     @type string   $start_time      ISO 8601 (required)
     *     @type string   $end_time        ISO 8601 (required)
     *     @type string[] $destination_ips (optional, default: all your protected IPs)
     *     @type string   $interval        10s, 30s, 1m (default), 5m, 15m or 1h
     * }
     * @return array Contains start_time, end_time, interval, total_pass, total_drop, buckets
     */
    public function getTrafficStats(array $filters): array
    {
        return $this->client->post('/ddos-mitigation/traffic-capture/stats', $filters);
    }
}
