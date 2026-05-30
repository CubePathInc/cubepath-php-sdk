<?php

namespace Cubepath\Tests;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Middleware;
use Cubepath\CubepathClient;
use Cubepath\APIError;

class CubepathClientTest extends TestCase
{
    private array $requestHistory = [];

    private function createClient(array $responses): CubepathClient
    {
        $this->requestHistory = [];
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->requestHistory));

        $httpClient = new HttpClient(['handler' => $stack]);

        return new CubepathClient('test-token-123', [
            'http_client' => $httpClient,
            'base_url' => 'https://api.cubepath.com',
            'max_retries' => 0,
            'rate_limit_per_sec' => 1000, // disable rate limiting in tests
        ]);
    }

    private function lastRequest(): \GuzzleHttp\Psr7\Request
    {
        return end($this->requestHistory)['request'];
    }

    // --- Client Tests ---

    public function testClientRequiresToken(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CubepathClient('');
    }

    public function testClientSetsAuthHeader(): void
    {
        $client = $this->createClient([
            new Response(200, [], '[]'),
        ]);
        $client->get('/test');

        $this->assertEquals(
            'Bearer test-token-123',
            $this->lastRequest()->getHeader('Authorization')[0]
        );
    }

    public function testClientSetsUserAgent(): void
    {
        $client = $this->createClient([
            new Response(200, [], '[]'),
        ]);
        $client->get('/test');

        $this->assertStringStartsWith(
            'cubepath-sdk-php/',
            $this->lastRequest()->getHeader('User-Agent')[0]
        );
    }

    public function testApiErrorParsing(): void
    {
        $client = $this->createClient([
            new Response(404, [], '{"detail": "VPS not found"}'),
        ]);

        try {
            $client->get('/vps/999');
            $this->fail('Expected APIError');
        } catch (APIError $e) {
            $this->assertEquals(404, $e->getStatusCode());
            $this->assertEquals('VPS not found', $e->getDetail());
            $this->assertTrue($e->isNotFound());
            $this->assertFalse($e->isRateLimited());
        }
    }

    public function testEmptyResponseReturnsEmptyArray(): void
    {
        $client = $this->createClient([
            new Response(204, [], ''),
        ]);

        $result = $client->delete('/test');
        $this->assertEquals([], $result);
    }

    // --- Service Initialization Tests ---

    public function testAllServicesInitialize(): void
    {
        $client = $this->createClient([]);

        $this->assertInstanceOf(\Cubepath\Services\VPSService::class, $client->vps());
        $this->assertInstanceOf(\Cubepath\Services\DNSService::class, $client->dns());
        $this->assertInstanceOf(\Cubepath\Services\SSHKeyService::class, $client->sshKeys());
        $this->assertInstanceOf(\Cubepath\Services\FirewallService::class, $client->firewall());
        $this->assertInstanceOf(\Cubepath\Services\FloatingIPService::class, $client->floatingIPs());
        $this->assertInstanceOf(\Cubepath\Services\NetworkService::class, $client->networks());
        $this->assertInstanceOf(\Cubepath\Services\ProjectService::class, $client->projects());
        $this->assertInstanceOf(\Cubepath\Services\PricingService::class, $client->pricing());
        $this->assertInstanceOf(\Cubepath\Services\BaremetalService::class, $client->baremetal());
        $this->assertInstanceOf(\Cubepath\Services\LoadBalancerService::class, $client->loadBalancer());
        $this->assertInstanceOf(\Cubepath\Services\CDNService::class, $client->cdn());
        $this->assertInstanceOf(\Cubepath\Services\KubernetesService::class, $client->kubernetes());
        $this->assertInstanceOf(\Cubepath\Services\DDoSService::class, $client->ddos());
        $this->assertInstanceOf(\Cubepath\Services\AIGatewayService::class, $client->aiGateway());
        $this->assertInstanceOf(\Cubepath\Services\NatGatewayService::class, $client->natGateway());
    }

    public function testServicesSingleton(): void
    {
        $client = $this->createClient([]);
        $this->assertSame($client->vps(), $client->vps());
        $this->assertSame($client->dns(), $client->dns());
    }

    // --- VPS Service Tests ---

    public function testVPSCreate(): void
    {
        $client = $this->createClient([
            new Response(200, [], '{"task_id": "abc", "detail": "Creating VPS"}'),
        ]);

        $result = $client->vps()->create(1, [
            'name' => 'test-server',
            'plan_name' => 'rz.nano',
            'template_name' => 'ubuntu-24',
            'location_name' => 'us-mia-1',
        ]);

        $req = $this->lastRequest();
        $this->assertEquals('POST', $req->getMethod());
        $this->assertEquals('https://api.cubepath.com/vps/create/1', (string) $req->getUri());
        $body = json_decode((string) $req->getBody(), true);
        $this->assertEquals('test-server', $body['name']);
        $this->assertEquals('rz.nano', $body['plan_name']);
        $this->assertEquals('abc', $result['task_id']);
    }

    public function testVPSList(): void
    {
        $client = $this->createClient([
            new Response(200, [], '[{"project": {"id": 1}, "vps": [{"id": 10}]}]'),
        ]);

        $client->vps()->list();
        $req = $this->lastRequest();
        $this->assertEquals('GET', $req->getMethod());
        $this->assertEquals('https://api.cubepath.com/projects/', (string) $req->getUri());
    }

    public function testVPSGet(): void
    {
        $client = $this->createClient([
            new Response(200, [], json_encode([
                ['project' => ['id' => 1], 'vps' => [['id' => 10, 'name' => 'found']]],
                ['project' => ['id' => 2], 'vps' => [['id' => 20, 'name' => 'other']]],
            ])),
        ]);

        $vps = $client->vps()->get(10);
        $this->assertEquals('found', $vps['name']);
    }

    public function testVPSGetNotFound(): void
    {
        $client = $this->createClient([
            new Response(200, [], '[{"project": {"id": 1}, "vps": []}]'),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('VPS 999 not found');
        $client->vps()->get(999);
    }

    public function testVPSDestroy(): void
    {
        $client = $this->createClient([
            new Response(200, [], '{"detail": "Server deleted"}'),
        ]);

        $client->vps()->destroy(10, false);
        $req = $this->lastRequest();
        $this->assertEquals('POST', $req->getMethod());
        $this->assertStringContainsString('/vps/destroy/10', (string) $req->getUri());
        $body = json_decode((string) $req->getBody(), true);
        $this->assertFalse($body['release_ips']);
    }

    public function testVPSPower(): void
    {
        $client = $this->createClient([
            new Response(200, [], '{"detail": "OK"}'),
        ]);

        $client->vps()->power(10, 'reboot');
        $req = $this->lastRequest();
        $this->assertEquals('POST', $req->getMethod());
        $this->assertEquals('https://api.cubepath.com/vps/10/power/reboot', (string) $req->getUri());
    }

    public function testVPSResize(): void
    {
        $client = $this->createClient([
            new Response(200, [], '{"detail": "Resize queued"}'),
        ]);

        $client->vps()->resize(10, 'rz.small');
        $req = $this->lastRequest();
        $this->assertEquals('POST', $req->getMethod());
        $this->assertEquals(
            'https://api.cubepath.com/vps/resize/vps_id/10/resize_plan/rz.small',
            (string) $req->getUri()
        );
    }

    public function testVPSReinstall(): void
    {
        $client = $this->createClient([
            new Response(200, [], '{"detail": "Reinstall queued"}'),
        ]);

        $client->vps()->reinstall(10, 'ubuntu-24');
        $body = json_decode((string) $this->lastRequest()->getBody(), true);
        $this->assertEquals('ubuntu-24', $body['template_name']);
    }

    // --- VPS Backup Tests ---

    public function testVPSBackupRestore(): void
    {
        $client = $this->createClient([
            new Response(200, [], '{"detail": "Restore queued"}'),
        ]);

        $client->vps()->backups()->restore(10, 5);
        $req = $this->lastRequest();
        $this->assertEquals('POST', $req->getMethod());
        $this->assertStringContainsString('/vps/10/backups/5/restore', (string) $req->getUri());
        $body = json_decode((string) $req->getBody(), true);
        $this->assertTrue($body['confirm']);
    }

    // --- VPS ISO Tests ---

    public function testVPSISOList(): void
    {
        $client = $this->createClient([
            new Response(200, [], '{"items": [], "mounted_iso_id": ""}'),
        ]);

        $client->vps()->isos()->list(10);
        $req = $this->lastRequest();
        $this->assertEquals('GET', $req->getMethod());
        $this->assertStringContainsString('/vps/10/isos', (string) $req->getUri());
    }

    public function testVPSISOMount(): void
    {
        $client = $this->createClient([
            new Response(200, [], '{"detail": "ISO mounted"}'),
        ]);

        $client->vps()->isos()->mount(10, 'iso-123');
        $req = $this->lastRequest();
        $this->assertEquals('POST', $req->getMethod());
        $this->assertStringContainsString('/vps/10/iso', (string) $req->getUri());
        $this->assertStringNotContainsString('/isos', (string) $req->getUri());
    }

    public function testVPSISOUnmount(): void
    {
        $client = $this->createClient([
            new Response(200, [], ''),
        ]);

        $client->vps()->isos()->unmount(10);
        $req = $this->lastRequest();
        $this->assertEquals('DELETE', $req->getMethod());
        $this->assertStringContainsString('/vps/10/iso', (string) $req->getUri());
    }

    // --- Firewall Tests ---

    public function testFirewallCreate(): void
    {
        $client = $this->createClient([
            new Response(200, [], '{"id": 1, "name": "test"}'),
        ]);

        $client->firewall()->create(['name' => 'test', 'rules' => [], 'enabled' => true]);
        $req = $this->lastRequest();
        $this->assertEquals('POST', $req->getMethod());
        $this->assertStringContainsString('/firewall/groups', (string) $req->getUri());
    }

    public function testFirewallUpdate(): void
    {
        $client = $this->createClient([
            new Response(200, [], '{"id": 1}'),
        ]);

        $client->firewall()->update(1, ['name' => 'updated']);
        $req = $this->lastRequest();
        $this->assertEquals('PATCH', $req->getMethod());
    }

    public function testFirewallAssignToVPS(): void
    {
        $client = $this->createClient([
            new Response(200, [], '{"message": "OK", "sync_task_created": true}'),
        ]);

        $client->firewall()->assignToVPS(10, [1, 2]);
        $req = $this->lastRequest();
        $this->assertEquals('POST', $req->getMethod());
        $this->assertStringContainsString('/vps/10/firewall-groups', (string) $req->getUri());
    }

    // --- Floating IP Tests ---

    public function testFloatingIPAcquire(): void
    {
        $client = $this->createClient([
            new Response(200, [], '{"id": 1, "address": "1.2.3.4"}'),
        ]);

        $client->floatingIPs()->acquire('ipv4', 'us-mia-1');
        $req = $this->lastRequest();
        $this->assertEquals('POST', $req->getMethod());
        $this->assertStringContainsString('ip_type=ipv4', (string) $req->getUri());
        $this->assertStringContainsString('location_name=us-mia-1', (string) $req->getUri());
    }

    public function testFloatingIPAssign(): void
    {
        $client = $this->createClient([
            new Response(200, [], '{"detail": "OK"}'),
        ]);

        $client->floatingIPs()->assign('vps', 10, '1.2.3.4');
        $req = $this->lastRequest();
        $this->assertStringContainsString('/floating_ips/assign/vps/10', (string) $req->getUri());
        $this->assertStringContainsString('address=1.2.3.4', (string) $req->getUri());
    }

    public function testFloatingIPReverseDNS(): void
    {
        $client = $this->createClient([
            new Response(200, [], '{"detail": "OK"}'),
        ]);

        $client->floatingIPs()->configureReverseDNS('1.2.3.4', 'host.example.com');
        $req = $this->lastRequest();
        $this->assertStringContainsString('/floating_ips/reverse_dns/configure', (string) $req->getUri());
        $this->assertStringContainsString('ip=1.2.3.4', (string) $req->getUri());
    }

    // --- Network Tests ---

    public function testNetworkCreate(): void
    {
        $client = $this->createClient([
            new Response(200, [], '{"id": 1}'),
        ]);

        $client->networks()->create(['name' => 'test', 'location_name' => 'us-mia-1']);
        $req = $this->lastRequest();
        $this->assertStringContainsString('/networks/create_network', (string) $req->getUri());
    }

    public function testNetworkList(): void
    {
        $client = $this->createClient([
            new Response(200, [], '[]'),
        ]);

        $client->networks()->list();
        $req = $this->lastRequest();
        $this->assertStringContainsString('/projects/', (string) $req->getUri());
    }

    // --- Baremetal Tests ---

    public function testBaremetalDeploy(): void
    {
        $client = $this->createClient([
            new Response(200, [], '{"task_id": "abc"}'),
        ]);

        $client->baremetal()->deploy(1, [
            'model_name' => 'e3-1270',
            'location_name' => 'us-mia-1',
            'hostname' => 'bm-1',
            'password' => 'test1234',
        ]);
        $req = $this->lastRequest();
        $this->assertEquals('POST', $req->getMethod());
        $this->assertStringContainsString('/baremetal/deploy/1', (string) $req->getUri());
    }

    public function testBaremetalGetSearchesProjects(): void
    {
        $client = $this->createClient([
            new Response(200, [], json_encode([
                ['project' => ['id' => 1], 'baremetals' => [['id' => 5, 'hostname' => 'bm-found']]],
            ])),
        ]);

        $bm = $client->baremetal()->get(5);
        $this->assertEquals('bm-found', $bm['hostname']);
    }

    public function testBaremetalPower(): void
    {
        $client = $this->createClient([
            new Response(200, [], '{"detail": "OK"}'),
        ]);

        $client->baremetal()->power(5, 'reboot');
        $this->assertStringContainsString('/baremetal/5/power/reboot', (string) $this->lastRequest()->getUri());
    }

    public function testBaremetalIPMISession(): void
    {
        $client = $this->createClient([
            new Response(200, [], '{"proxy_url": "https://...", "credentials": {}}'),
        ]);

        $client->baremetal()->ipmiSession(5);
        $this->assertStringContainsString('/ipmi-proxy/create-session/5', (string) $this->lastRequest()->getUri());
    }

    public function testBaremetalMonitoring(): void
    {
        $client = $this->createClient([
            new Response(200, [], '{"detail": "OK"}'),
            new Response(200, [], '{"detail": "OK"}'),
        ]);

        $client->baremetal()->monitoringEnable(5);
        $this->assertStringContainsString('/baremetal/5/monitoring?enable=true', (string) $this->lastRequest()->getUri());
        $this->assertEquals('PUT', $this->lastRequest()->getMethod());

        $client->baremetal()->monitoringDisable(5);
        $this->assertStringContainsString('/baremetal/5/monitoring?enable=false', (string) $this->lastRequest()->getUri());
    }

    // --- Load Balancer Tests ---

    public function testLBCreate(): void
    {
        $client = $this->createClient([
            new Response(200, [], '{"uuid": "lb-1"}'),
        ]);

        $client->loadBalancer()->create(['name' => 'test-lb', 'plan_name' => 'lb.small', 'location_name' => 'us-mia-1']);
        $req = $this->lastRequest();
        $this->assertEquals('POST', $req->getMethod());
        $this->assertStringEndsWith('/loadbalancer/', (string) $req->getUri());
    }

    public function testLBDrainTarget(): void
    {
        $client = $this->createClient([
            new Response(200, [], '{"detail": "OK"}'),
        ]);

        $client->loadBalancer()->drainTarget('lb-1', 'l-1', 't-1');
        $this->assertStringContainsString(
            '/loadbalancer/lb-1/listeners/l-1/targets/t-1/drain',
            (string) $this->lastRequest()->getUri()
        );
    }

    public function testLBHealthCheck(): void
    {
        $client = $this->createClient([
            new Response(200, [], '{"detail": "OK"}'),
        ]);

        $client->loadBalancer()->configureHealthCheck('lb-1', 'l-1', [
            'protocol' => 'http',
            'path' => '/health',
        ]);
        $req = $this->lastRequest();
        $this->assertEquals('PUT', $req->getMethod());
        $this->assertStringContainsString('/loadbalancer/lb-1/listeners/l-1/health-check', (string) $req->getUri());
    }

    // --- Kubernetes Tests ---

    public function testK8sCreate(): void
    {
        $client = $this->createClient([
            new Response(200, [], '{"uuid": "k8s-1", "detail": "Creating"}'),
        ]);

        $client->kubernetes()->create([
            'project_id' => 1,
            'name' => 'test-cluster',
            'location_name' => 'us-mia-1',
            'ha_control_plane' => false,
            'node_pools' => [['name' => 'pool-1', 'plan' => 'rz.small', 'count' => 2]],
        ]);
        $req = $this->lastRequest();
        $this->assertEquals('POST', $req->getMethod());
        $this->assertStringEndsWith('/kubernetes/', (string) $req->getUri());
    }

    public function testK8sNodePoolAddNodes(): void
    {
        $client = $this->createClient([
            new Response(200, [], '{"detail": "OK"}'),
        ]);

        $client->kubernetes()->addNodes('k8s-1', 'pool-1', 3);
        $body = json_decode((string) $this->lastRequest()->getBody(), true);
        $this->assertEquals(3, $body['count']);
        $this->assertStringContainsString('/kubernetes/k8s-1/node-pools/pool-1/nodes', (string) $this->lastRequest()->getUri());
    }

    public function testK8sInstallAddon(): void
    {
        $client = $this->createClient([
            new Response(200, [], '{"detail": "OK"}'),
        ]);

        $client->kubernetes()->installAddon('k8s-1', 'metrics-server', ['key' => 'value']);
        $body = json_decode((string) $this->lastRequest()->getBody(), true);
        $this->assertEquals(['key' => 'value'], $body['custom_values']);
        $this->assertStringContainsString('/kubernetes/k8s-1/addons/metrics-server/install', (string) $this->lastRequest()->getUri());
    }

    // --- CDN Tests ---

    public function testCDNWAFRules(): void
    {
        $client = $this->createClient([
            new Response(200, [], '[]'),
        ]);

        $client->cdn()->listWAFRules('zone-1');
        $this->assertStringContainsString('/cdn/zones/zone-1/waf-rules', (string) $this->lastRequest()->getUri());
    }

    // --- DDoS Tests ---

    public function testDDoSListAttacks(): void
    {
        $client = $this->createClient([
            new Response(200, [], '[]'),
        ]);

        $client->ddos()->listAttacks();
        $this->assertStringContainsString('/ddos-attacks/attacks', (string) $this->lastRequest()->getUri());
    }

    // --- AI Gateway Tests ---

    public function testAIGatewayListModels(): void
    {
        $client = $this->createClient([
            new Response(200, [], '{"object": "list", "data": []}'),
        ]);

        $result = $client->aiGateway()->listModels();
        $req = $this->lastRequest();
        $this->assertStringContainsString('ai-gateway.cubepath.com/models', (string) $req->getUri());
        $this->assertEquals('list', $result['object']);
    }

    public function testAIGatewayChatCompletion(): void
    {
        $client = $this->createClient([
            new Response(200, [], '{"id": "chat-1", "choices": []}'),
        ]);

        $result = $client->aiGateway()->chatCompletion([
            'model' => 'openai/gpt-4o',
            'messages' => [['role' => 'user', 'content' => 'hello']],
        ]);
        $req = $this->lastRequest();
        $this->assertStringContainsString('ai-gateway.cubepath.com/chat/completions', (string) $req->getUri());
        $body = json_decode((string) $req->getBody(), true);
        $this->assertFalse($body['stream']);
    }
}
