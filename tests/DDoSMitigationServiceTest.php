<?php

namespace Cubepath\Tests;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Middleware;
use Cubepath\CubepathClient;

class DDoSMitigationServiceTest extends TestCase
{
    private array $requestHistory = [];

    private function createClient(array $responses): CubepathClient
    {
        $this->requestHistory = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->requestHistory));

        return new CubepathClient('test-token-123', [
            'http_client' => new HttpClient(['handler' => $stack]),
            'max_retries' => 0,
            'rate_limit_per_sec' => 1000,
        ]);
    }

    private function lastRequest(): \GuzzleHttp\Psr7\Request
    {
        return end($this->requestHistory)['request'];
    }

    private function lastBody()
    {
        return json_decode((string) $this->lastRequest()->getBody(), true);
    }

    private function ok(): Response
    {
        return new Response(200, [], '{"detail":"ok"}');
    }

    public function testIPsAndCatalogs(): void
    {
        $client = $this->createClient([
            new Response(200, [], '{"single_ips":[],"subnets":[],"total":0}'),
            new Response(200, [], '{"countries":[{"iso_code":"ES"}],"total":1}'),
            new Response(200, [], '{"asns":[],"total":0}'),
        ]);
        $d = $client->ddosMitigation();

        $this->assertEquals(0, $d->listIPs(['has_profile' => false, 'ip_type' => 'ipv4'])['total']);
        $this->assertEquals('/ddos-mitigation/ips', $this->lastRequest()->getUri()->getPath());
        $this->assertEquals('has_profile=false&ip_type=ipv4', $this->lastRequest()->getUri()->getQuery());

        $d->listCountries();
        $this->assertEquals('/ddos-mitigation/countries', $this->lastRequest()->getUri()->getPath());

        $d->listASNs('google');
        $this->assertEquals('search=google', $this->lastRequest()->getUri()->getQuery());
    }

    public function testProfileKeepsTheCIDRSlash(): void
    {
        $client = $this->createClient([
            new Response(200, [], '{"tcp_validation_level":1}'),
            $this->ok(),
            $this->ok(),
        ]);
        $d = $client->ddosMitigation();

        $d->getProfile('203.0.113.0/24');
        $this->assertEquals('/ddos-mitigation/profiles/203.0.113.0/24', $this->lastRequest()->getUri()->getPath());

        $d->updateProfile('203.0.113.10', ['udp_validation_level' => 2]);
        $this->assertEquals('PUT', $this->lastRequest()->getMethod());
        $this->assertEquals(['udp_validation_level' => 2], $this->lastBody());

        $d->deleteProfile('203.0.113.10');
        $this->assertEquals('DELETE', $this->lastRequest()->getMethod());
        $this->assertEquals('/ddos-mitigation/profiles/203.0.113.10', $this->lastRequest()->getUri()->getPath());
    }

    public function testProfileAssignments(): void
    {
        $client = $this->createClient([
            new Response(200, [], '{"countries":[],"total":0}'),
            $this->ok(),
            new Response(200, [], '{"asns":[],"total":0}'),
            $this->ok(),
            new Response(200, [], '{"prefix_lists":[],"total":0}'),
            $this->ok(),
        ]);
        $d = $client->ddosMitigation();
        $ip = '203.0.113.10';

        $d->getProfileCountries($ip);
        $this->assertEquals("/ddos-mitigation/profiles/{$ip}/countries", $this->lastRequest()->getUri()->getPath());
        $d->setProfileCountries($ip, ['CN']);
        $this->assertEquals(['iso_codes' => ['CN']], $this->lastBody());

        $d->getProfileASNs($ip);
        $this->assertEquals("/ddos-mitigation/profiles/{$ip}/asns", $this->lastRequest()->getUri()->getPath());
        $d->setProfileASNs($ip, [15169]);
        $this->assertEquals(['asns' => [15169]], $this->lastBody());

        $d->getProfilePrefixLists($ip);
        $this->assertEquals("/ddos-mitigation/profiles/{$ip}/prefix-lists", $this->lastRequest()->getUri()->getPath());
        $d->setProfilePrefixLists($ip, []);
        $this->assertEquals('PUT', $this->lastRequest()->getMethod());
        $this->assertEquals(['uuids' => []], $this->lastBody());
    }

    public function testFirewallRules(): void
    {
        $client = $this->createClient([
            new Response(200, [], '{"rules":[],"total":0}'),
            new Response(201, [], '{"detail":"created"}'),
            $this->ok(),
            $this->ok(),
        ]);
        $d = $client->ddosMitigation();

        $d->listFirewallRules('203.0.113.0/28');
        $this->assertEquals('/ddos-mitigation/firewall-rules/203.0.113.0/28', $this->lastRequest()->getUri()->getPath());

        $d->createFirewallRule(['network' => '203.0.113.10', 'protocol' => 6, 'dst_port' => 22, 'action' => 0]);
        $this->assertEquals('/ddos-mitigation/firewall-rules', $this->lastRequest()->getUri()->getPath());
        $this->assertEquals(22, $this->lastBody()['dst_port']);

        $d->deleteFirewallRule(7);
        $this->assertEquals('/ddos-mitigation/firewall-rules/7', $this->lastRequest()->getUri()->getPath());

        $d->deleteFirewallRulesBulk('203.0.113.0/28', 6, 22);
        $this->assertEquals('/ddos-mitigation/firewall-rules/bulk', $this->lastRequest()->getUri()->getPath());
        $this->assertEquals('network=203.0.113.0%2F28&protocol=6&dst_port=22', $this->lastRequest()->getUri()->getQuery());
    }

    public function testPrefixLists(): void
    {
        $client = $this->createClient([
            new Response(200, [], '{"prefix_lists":[{"uuid":"pl1"}],"total":1}'),
            new Response(201, [], '{"detail":"Prefix list created successfully"}'),
            new Response(200, [], '[{"network":"198.51.100.0/24"}]'),
            new Response(201, [], '{"detail":"added"}'),
            $this->ok(),
            $this->ok(),
        ]);
        $d = $client->ddosMitigation();

        $this->assertEquals('pl1', $d->listPrefixLists()['prefix_lists'][0]['uuid']);
        $d->createPrefixList('office', 'HQ ranges');
        $this->assertEquals(['name' => 'office', 'description' => 'HQ ranges'], $this->lastBody());

        $d->listPrefixListEntries('pl1');
        $this->assertEquals('/ddos-mitigation/prefix-lists/pl1/entries', $this->lastRequest()->getUri()->getPath());
        $d->addPrefixListEntry('pl1', '198.51.100.0/24');
        $this->assertEquals(['network' => '198.51.100.0/24'], $this->lastBody());
        $d->deletePrefixListEntry('pl1', '198.51.100.0/24');
        $this->assertEquals('/ddos-mitigation/prefix-lists/pl1/entries/198.51.100.0/24', $this->lastRequest()->getUri()->getPath());

        $d->deletePrefixList('pl1');
        $this->assertEquals('DELETE', $this->lastRequest()->getMethod());
        $this->assertEquals('/ddos-mitigation/prefix-lists/pl1', $this->lastRequest()->getUri()->getPath());
    }

    public function testTrafficCapture(): void
    {
        $client = $this->createClient([
            new Response(200, [], '{"total":0,"ips":[]}'),
            new Response(200, [], '{"total_logs":0,"logs":[]}'),
            new Response(200, [], '{"total_pass":0,"total_drop":0,"buckets":[]}'),
        ]);
        $d = $client->ddosMitigation();

        $d->listCaptureIPs();
        $this->assertEquals('/ddos-mitigation/traffic-capture/protected-ips', $this->lastRequest()->getUri()->getPath());

        $filters = ['start_time' => '2026-09-30T00:00:00Z', 'end_time' => '2026-09-30T01:00:00Z', 'destination_ip' => '203.0.113.10'];
        $d->queryTrafficCapture($filters);
        $this->assertEquals('POST', $this->lastRequest()->getMethod());
        $this->assertEquals($filters, $this->lastBody());

        $d->getTrafficStats(['start_time' => 'a', 'end_time' => 'b', 'interval' => '5m']);
        $this->assertEquals('/ddos-mitigation/traffic-capture/stats', $this->lastRequest()->getUri()->getPath());
    }

    public function testAttackDetailsAndGraph(): void
    {
        $client = $this->createClient([new Response(200, [], '{}'), new Response(200, [], '{}')]);

        $client->ddos()->getAttackDetails(4211);
        $this->assertEquals('/ddos-attacks/attacks/4211/details', $this->lastRequest()->getUri()->getPath());
        $client->ddos()->getAttackTrafficGraph(4211);
        $this->assertEquals('/ddos-attacks/attacks/4211/traffic-graph', $this->lastRequest()->getUri()->getPath());
    }
}
