<?php

namespace Cubepath\Tests;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Middleware;
use Cubepath\APIError;
use Cubepath\CubepathClient;

class StaleEndpointsTest extends TestCase
{
    private array $history = [];

    private function client(array $responses): CubepathClient
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));
        return new CubepathClient('t', [
            'http_client' => new HttpClient(['handler' => $stack]),
            'max_retries' => 0,
            'rate_limit_per_sec' => 1000,
        ]);
    }

    private function req(int $i): \GuzzleHttp\Psr7\Request
    {
        return $this->history[$i]['request'];
    }

    private function body(int $i): array
    {
        return json_decode((string) $this->req($i)->getBody(), true) ?? [];
    }

    public function testAssignToVpsUsesFirewallRoute(): void
    {
        $c = $this->client([new Response(200, [], '{"detail":"ok","vps_id":7,"firewall_groups":[3],"sync_task_created":true}')]);
        $res = $c->firewall()->assignToVPS(7, [3]);
        $this->assertEquals('PUT', $this->req(0)->getMethod());
        $this->assertEquals('/firewall/vps/7/groups', $this->req(0)->getUri()->getPath());
        $this->assertEquals([3], $this->body(0)['firewall_group_ids']);
        $this->assertEquals('ok', $res['detail']);
    }

    public function testCreateFirewallGroupSendsProjectIdInQuery(): void
    {
        $c = $this->client([new Response(201, [], '{"id":5}')]);
        $c->firewall()->create(['project_id' => 12, 'name' => 'web', 'rules' => [], 'enabled' => true]);
        $this->assertEquals('project_id=12', $this->req(0)->getUri()->getQuery());
        $this->assertArrayNotHasKey('project_id', $this->body(0));
        $this->expectException(\InvalidArgumentException::class);
        $c->firewall()->create(['name' => 'web']);
    }

    public function testFirewallUpdateUsesPutAndGetUsesList(): void
    {
        $c = $this->client([
            new Response(200, [], '{"id":5,"name":"new"}'),
            new Response(200, [], '[{"id":4,"name":"a"},{"id":5,"name":"b"}]'),
            new Response(200, [], '[]'),
        ]);
        $c->firewall()->update(5, ['name' => 'new']);
        $g = $c->firewall()->get(5);
        $this->assertEquals('PUT', $this->req(0)->getMethod());
        $this->assertEquals('/firewall/groups', $this->req(1)->getUri()->getPath());
        $this->assertEquals('b', $g['name']);
        try {
            $c->firewall()->get(99);
            $this->fail('expected APIError');
        } catch (APIError $e) {
            $this->assertEquals(404, $e->getStatusCode());
        }
    }

    public function testLoadBalancerAndProjectGetUseLists(): void
    {
        $c = $this->client([
            new Response(200, [], '[{"uuid":"a","name":"x"},{"uuid":"b","name":"y"}]'),
            new Response(200, [], '[{"project":{"id":12,"name":"p"},"vps":[],"baremetals":[],"networks":[]}]'),
        ]);
        $this->assertEquals('y', $c->loadBalancer()->get('b')['name']);
        $this->assertEquals('p', $c->projects()->get(12)['project']['name']);
        $this->assertEquals('/loadbalancer/', $this->req(0)->getUri()->getPath());
        // /projects without the slash redirects to http:// and loses the Authorization header.
        $this->assertEquals('/projects/', $this->req(1)->getUri()->getPath());
    }

    public function testNatMetricsAndBandwidthViaGraphql(): void
    {
        $c = $this->client([
            new Response(200, [], '{"data":{"natGateway":{"metrics":{"start":1,"end":2,"step":30,"series":[]}}}}'),
            new Response(200, [], '{"data":{"natGateway":{"bandwidthUsage":{"inBytes":1,"outBytes":2,"totalBytes":3,"periodStart":0,"periodEnd":1}}}}'),
        ]);
        $m = $c->natGateway()->getMetrics('u1', 'H24');
        $b = $c->natGateway()->getBandwidthUsage('u1');
        $this->assertEquals('/graphql', $this->req(0)->getUri()->getPath());
        $this->assertEquals(['uuid' => 'u1', 'range' => 'H24'], $this->body(0)['variables']);
        $this->assertEquals(30, $m['step']);
        $this->assertEquals(3, $b['totalBytes']);
    }

    public function testGraphqlNotFoundIs404(): void
    {
        $c = $this->client([new Response(200, [], '{"data":{"natGateway":null},"errors":[{"message":"Resource not found.","extensions":{"code":"NOT_FOUND"}}]}')]);
        try {
            $c->natGateway()->getMetrics('nope');
            $this->fail('expected APIError');
        } catch (APIError $e) {
            $this->assertEquals(404, $e->getStatusCode());
        }
    }

    public function testBmcSensorsViaGraphqlAndReinstall(): void
    {
        $c = $this->client([
            new Response(200, [], '{"data":{"baremetal":{"sensors":{"ipmiAvailable":true,"powerOn":true,"lastSeen":100,"temperatures":[{"name":"CPU","value":41,"unit":"CELSIUS"}],"fans":[]}}}}'),
            new Response(200, [], '[{"project":{"id":1},"baremetals":[{"id":9,"status":"deploying"}]}]'),
            new Response(200, [], '{"detail":"cancelled"}'),
        ]);
        $s = $c->baremetal()->bmcSensors(9);
        $this->assertEquals(['id' => '9'], $this->body(0)['variables']);
        $this->assertTrue($s['ipmi_available']);
        $this->assertEquals(100, $s['last_seen']);
        $this->assertEquals('CELSIUS', $s['sensors']['temperatures'][0]['unit']);
        $st = $c->baremetal()->reinstallStatus(9);
        $this->assertTrue($st['is_reinstalling']);
        $c->baremetal()->cancelReinstall(9);
        $this->assertEquals('DELETE', $this->req(2)->getMethod());
        $this->assertEquals('/baremetal/9/reinstall', $this->req(2)->getUri()->getPath());
    }
}
