<?php

namespace Cubepath\Tests;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Middleware;
use Cubepath\CubepathClient;

class ResourceActionsTest extends TestCase
{
    private array $requestHistory = [];

    private function createClient(int $responses): CubepathClient
    {
        $this->requestHistory = [];
        $queue = [];
        for ($i = 0; $i < $responses; $i++) {
            $queue[] = new Response(200, [], '{"detail":"ok"}');
        }
        $stack = HandlerStack::create(new MockHandler($queue));
        $stack->push(Middleware::history($this->requestHistory));

        return new CubepathClient('test-token-123', [
            'http_client' => new HttpClient(['handler' => $stack]),
            'max_retries' => 0,
            'rate_limit_per_sec' => 1000,
        ]);
    }

    /**
     * Assert the last request's method, path, query and JSON body.
     */
    private function assertLast(string $method, string $path, $body = null, string $query = ''): void
    {
        $req = end($this->requestHistory)['request'];
        $this->assertEquals($method, $req->getMethod());
        $this->assertEquals($path, $req->getUri()->getPath());
        $this->assertEquals($query, $req->getUri()->getQuery());
        $this->assertEquals($body, json_decode((string) $req->getBody(), true));
    }

    public function testVPSActions(): void
    {
        $client = $this->createClient(9);
        $vps = $client->vps();

        $vps->plans();
        $this->assertLast('GET', '/vps/plans');
        $vps->listAll();
        $this->assertLast('GET', '/vps/');
        $vps->protection(10, true);
        $this->assertLast('POST', '/vps/10/protection', ['enabled' => true]);
        $vps->moveToProject(10, 3);
        $this->assertLast('POST', '/vps/10/move-project', ['project_id' => 3]);
        $vps->addSSHKeys(10, ['5', 6]);
        $this->assertLast('POST', '/vps/10/ssh-keys', [5, 6]);
        $vps->removeSSHKey(10, 5);
        $this->assertLast('DELETE', '/vps/10/ssh-keys/5');
        $vps->attachNetwork(10, 7);
        $this->assertLast('POST', '/vps/10/network', ['network_id' => 7]);
        $vps->detachNetwork(10);
        $this->assertLast('DELETE', '/vps/10/network');
        $vps->vncUrl(10);
        $this->assertLast('POST', '/vps/10/vnc-url');
    }

    public function testVPSBackupsPagination(): void
    {
        $client = $this->createClient(2);

        $client->vps()->backups()->list(10);
        $this->assertLast('GET', '/vps/10/backups', null, 'limit=50&offset=0');
        $client->vps()->backups()->list(10, 5, 10);
        $this->assertLast('GET', '/vps/10/backups', null, 'limit=5&offset=10');
    }

    public function testAvailabilityGroups(): void
    {
        $client = $this->createClient(8);
        $ag = $client->vps()->availabilityGroups();

        $ag->create(['project_id' => 1, 'name' => 'web', 'location_name' => 'eu-bcn-1']);
        $this->assertLast('POST', '/vps/availability-groups/', ['project_id' => 1, 'name' => 'web', 'location_name' => 'eu-bcn-1']);
        $ag->list(1);
        $this->assertLast('GET', '/vps/availability-groups/project/1');
        $ag->list(1, 'eu-bcn-1');
        $this->assertLast('GET', '/vps/availability-groups/project/1', null, 'location_name=eu-bcn-1');
        $ag->get('g1');
        $this->assertLast('GET', '/vps/availability-groups/g1');
        $ag->addVPS('g1', 10);
        $this->assertLast('POST', '/vps/availability-groups/g1/vps/10');
        $ag->removeVPS('g1', 10);
        $this->assertLast('DELETE', '/vps/availability-groups/g1/vps/10');
        $ag->moveToProject('g1', 2);
        $this->assertLast('POST', '/vps/availability-groups/g1/move-project', ['project_id' => 2]);
        $ag->delete('g1');
        $this->assertLast('DELETE', '/vps/availability-groups/g1');
    }

    public function testBaremetalActions(): void
    {
        $client = $this->createClient(10);
        $bm = $client->baremetal();

        $bm->listAll();
        $this->assertLast('GET', '/baremetal/');
        $bm->models();
        $this->assertLast('GET', '/baremetal/models');
        $bm->listOS(4);
        $this->assertLast('GET', '/baremetal/os/4');
        $bm->kvm(4);
        $this->assertLast('GET', '/baremetal/4/kvm');
        $bm->protection(4, false);
        $this->assertLast('POST', '/baremetal/4/protection', ['enabled' => false]);
        $bm->moveToProject(4, 3);
        $this->assertLast('POST', '/baremetal/4/move-project', ['project_id' => 3]);
        $bm->addSSHKeys(4, [5]);
        $this->assertLast('POST', '/baremetal/4/ssh-keys', [5]);
        $bm->removeSSHKey(4, 5);
        $this->assertLast('DELETE', '/baremetal/4/ssh-keys/5');
        $bm->attachNetwork(4, 7);
        $this->assertLast('POST', '/baremetal/4/network', ['network_id' => 7]);
        $bm->detachNetwork(4);
        $this->assertLast('DELETE', '/baremetal/4/network');
    }

    public function testNetworkBGPPeersAndMove(): void
    {
        $client = $this->createClient(5);
        $net = $client->networks();

        $net->moveToProject(7, 3);
        $this->assertLast('POST', '/networks/7/move-project', ['project_id' => 3]);
        $net->listBGPPeers(7);
        $this->assertLast('GET', '/networks/7/bgp-peers');
        $net->createBGPPeer(7, ['peer_type' => 'vps', 'peer_target' => '10', 'remote_asn' => 65010]);
        $this->assertLast('POST', '/networks/7/bgp-peers', ['peer_type' => 'vps', 'peer_target' => '10', 'remote_asn' => 65010]);
        $net->updateBGPPeer(7, 'p1', ['enabled' => false]);
        $this->assertLast('PATCH', '/networks/7/bgp-peers/p1', ['enabled' => false]);
        $net->deleteBGPPeer(7, 'p1');
        $this->assertLast('DELETE', '/networks/7/bgp-peers/p1');
    }

    public function testCDNPurgeAndTokenAuth(): void
    {
        $client = $this->createClient(5);
        $cdn = $client->cdn();

        $cdn->purgeCache('z1', ['paths' => ['/a.css']]);
        $this->assertLast('POST', '/cdn/zones/z1/purge-cache', ['paths' => ['/a.css']]);
        $cdn->listPurges('z1');
        $this->assertLast('GET', '/cdn/zones/z1/purge-cache');
        $cdn->rotateTokenSecret('z1');
        $this->assertLast('POST', '/cdn/zones/z1/token-auth/rotate-secret');
        $cdn->signURL('z1', '/v.mp4');
        $this->assertLast('POST', '/cdn/zones/z1/token-auth/sign-url', ['path' => '/v.mp4', 'expires_in' => 3600]);
        $cdn->signURL('z1', '/v.mp4', 60, '198.51.100.1');
        $this->assertLast('POST', '/cdn/zones/z1/token-auth/sign-url', ['path' => '/v.mp4', 'expires_in' => 60, 'client_ip' => '198.51.100.1']);
    }

    public function testCDNMetricsFilters(): void
    {
        $client = $this->createClient(2);

        $client->cdn()->getMetrics('z1', 'summary');
        $this->assertLast('GET', '/cdn/zones/z1/metrics/summary');
        $client->cdn()->getMetrics('z1', 'top-urls', ['minutes' => 1440, 'limit' => 5, 'country' => 'ES', 'cache_status' => 'MISS']);
        $this->assertLast('GET', '/cdn/zones/z1/metrics/top-urls', null, 'minutes=1440&limit=5&country=ES&cache_status=MISS');
    }

    public function testDNSHealthChecks(): void
    {
        $client = $this->createClient(5);
        $dns = $client->dns();

        $dns->listRegions();
        $this->assertLast('GET', '/dns/regions');
        $dns->listHealthChecks('z1');
        $this->assertLast('GET', '/dns/zones/z1/health-checks');
        $dns->getHealthCheck('z1', 'r1');
        $this->assertLast('GET', '/dns/zones/z1/records/r1/health-check');
        $dns->setHealthCheck('z1', 'r1', ['name' => 'web', 'check_type' => 'https', 'path' => '/health']);
        $this->assertLast('PUT', '/dns/zones/z1/records/r1/health-check', ['name' => 'web', 'check_type' => 'https', 'path' => '/health']);
        $dns->deleteHealthCheck('z1', 'r1');
        $this->assertLast('DELETE', '/dns/zones/z1/records/r1/health-check');
    }

    public function testLoadBalancerActions(): void
    {
        $client = $this->createClient(3);
        $lb = $client->loadBalancer();

        $targets = [['target_type' => 'vps', 'target_uuid' => '10', 'port' => 80]];
        $lb->addTargets('lb1', 'l1', $targets);
        $this->assertLast('POST', '/loadbalancer/lb1/listeners/l1/targets/batch', ['targets' => $targets]);
        $lb->protection('lb1', true);
        $this->assertLast('POST', '/loadbalancer/lb1/protection', ['enabled' => true]);
        $lb->moveToProject('lb1', 3);
        $this->assertLast('POST', '/loadbalancer/lb1/move-project', ['project_id' => 3]);
    }

    public function testKubernetesActions(): void
    {
        $client = $this->createClient(3);
        $k8s = $client->kubernetes();

        $k8s->protection('c1', true);
        $this->assertLast('POST', '/kubernetes/c1/protection', ['enabled' => true]);
        $k8s->getMetrics('c1');
        $this->assertLast('GET', '/kubernetes/c1/metrics', null, 'time_range=1h');
        $k8s->getNodeMetrics('c1', 'worker-1', '24h');
        $this->assertLast('GET', '/kubernetes/c1/nodes/worker-1/metrics', null, 'time_range=24h');
    }

    public function testSSHKeyAndProjectUpdate(): void
    {
        $client = $this->createClient(2);

        $client->sshKeys()->update(5, 'laptop');
        $this->assertLast('PUT', '/sshkey/5', ['name' => 'laptop']);
        $client->projects()->update(3, 'prod');
        $this->assertLast('PUT', '/projects/3', ['name' => 'prod']);
    }
}
