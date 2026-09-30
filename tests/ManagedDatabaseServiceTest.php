<?php

namespace Cubepath\Tests;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Middleware;
use Cubepath\CubepathClient;

class ManagedDatabaseServiceTest extends TestCase
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

    public function testListPlansWithEngine(): void
    {
        $client = $this->createClient([new Response(200, [], '[{"location_name":"eu-bcn-1","plans":[{"uuid":"p1"}]}]')]);
        $plans = $client->managedDatabases()->listPlans('postgresql');

        $this->assertEquals('/managed-database-plans/', $this->lastRequest()->getUri()->getPath());
        $this->assertEquals('engine=postgresql', $this->lastRequest()->getUri()->getQuery());
        $this->assertEquals('p1', $plans[0]['plans'][0]['uuid']);
    }

    public function testInstanceLifecycle(): void
    {
        $client = $this->createClient([
            new Response(201, [], '{"uuid":"m1","status":"provisioning"}'),
            new Response(200, [], '[{"uuid":"m1"}]'),
            new Response(200, [], '{"uuid":"m1","plan":{"uuid":"p1"}}'),
            new Response(200, [], '{"detail":"Managed database updated successfully"}'),
            new Response(200, [], '{"detail":"Managed database protection enabled"}'),
            new Response(200, [], '{"detail":"Managed database scaling initiated","replicas":3}'),
            new Response(200, [], '{"detail":"Managed database deletion initiated"}'),
        ]);
        $md = $client->managedDatabases();

        $created = $md->create(['project_id' => 1, 'name' => 'db', 'engine' => 'postgresql', 'version' => '17.5.0', 'plan_uuid' => 'p1', 'replicas' => 2]);
        $this->assertEquals('POST', $this->lastRequest()->getMethod());
        $this->assertEquals('/managed-databases/', $this->lastRequest()->getUri()->getPath());
        $this->assertEquals(2, $this->lastBody()['replicas']);
        $this->assertEquals('provisioning', $created['status']);

        $md->list();
        $this->assertEquals('/managed-databases/', $this->lastRequest()->getUri()->getPath());

        $this->assertEquals('p1', $md->get('m1')['plan']['uuid']);
        $this->assertEquals('/managed-databases/m1', $this->lastRequest()->getUri()->getPath());

        $md->update('m1', ['label' => 'prod']);
        $this->assertEquals('PATCH', $this->lastRequest()->getMethod());
        $this->assertEquals(['label' => 'prod'], $this->lastBody());

        $md->protection('m1', true);
        $this->assertEquals('/managed-databases/m1/protection', $this->lastRequest()->getUri()->getPath());
        $this->assertEquals(['enabled' => true], $this->lastBody());

        $md->scale('m1', ['replicas' => 3]);
        $this->assertEquals('/managed-databases/m1/scale', $this->lastRequest()->getUri()->getPath());
        $this->assertEquals(['replicas' => 3], $this->lastBody());

        $md->delete('m1');
        $this->assertEquals('DELETE', $this->lastRequest()->getMethod());
        $this->assertEquals('/managed-databases/m1', $this->lastRequest()->getUri()->getPath());
    }

    public function testCredentialsConfigAndMetrics(): void
    {
        $client = $this->createClient([
            new Response(200, [], '{"host":"h","port":5432,"uri":"postgresql://u:p@h:5432"}'),
            new Response(200, [], '{"detail":"Credentials rotation initiated.","uuid":"m1"}'),
            new Response(200, [], '{"engine":"postgresql","params":{}}'),
            new Response(200, [], '{"detail":"ok","requires_restart":[]}'),
            new Response(200, [], '{"start":1,"end":2,"metrics":{"cpu":[]}}'),
        ]);
        $md = $client->managedDatabases();

        $this->assertEquals(5432, $md->getCredentials('m1')['port']);
        $this->assertEquals('/managed-databases/m1/credentials', $this->lastRequest()->getUri()->getPath());

        $md->rotateCredentials('m1');
        $this->assertEquals('POST', $this->lastRequest()->getMethod());
        $this->assertEquals('/managed-databases/m1/credentials/rotate', $this->lastRequest()->getUri()->getPath());

        $md->getConfig('m1');
        $this->assertEquals('/managed-databases/m1/config', $this->lastRequest()->getUri()->getPath());

        $md->updateConfig('m1', ['work_mem' => 8192]);
        $this->assertEquals('PATCH', $this->lastRequest()->getMethod());
        $this->assertEquals(['params' => ['work_mem' => 8192]], $this->lastBody());

        $md->getMetrics('m1', ['cpu', 'memory'], '24h');
        $this->assertEquals('/managed-databases/m1/metrics', $this->lastRequest()->getUri()->getPath());
        $this->assertEquals('time_range=24h&metrics=cpu%2Cmemory', $this->lastRequest()->getUri()->getQuery());
    }

    public function testDatabasesAndUsers(): void
    {
        $client = $this->createClient([
            new Response(201, [], '{"uuid":"d1","status":"pending"}'),
            new Response(200, [], '[{"uuid":"d1","name":"app"}]'),
            new Response(200, [], '{"detail":"deleted"}'),
            new Response(201, [], '{"uuid":"u1","password":"generated"}'),
            new Response(201, [], '{"uuid":"u2","password":"mine-long-enough"}'),
            new Response(200, [], '[{"uuid":"u1","username":"app"}]'),
            new Response(200, [], '{"detail":"deleted"}'),
        ]);
        $md = $client->managedDatabases();

        $md->createDatabase('m1', 'app');
        $this->assertEquals('/managed-databases/m1/databases', $this->lastRequest()->getUri()->getPath());
        $this->assertEquals(['name' => 'app'], $this->lastBody());
        $this->assertEquals('app', $md->listDatabases('m1')[0]['name']);
        $md->deleteDatabase('m1', 'd1');
        $this->assertEquals('DELETE', $this->lastRequest()->getMethod());
        $this->assertEquals('/managed-databases/m1/databases/d1', $this->lastRequest()->getUri()->getPath());

        $this->assertEquals('generated', $md->createUser('m1', 'app')['password']);
        $this->assertEquals(['username' => 'app'], $this->lastBody());
        $md->createUser('m1', 'other', 'mine-long-enough');
        $this->assertEquals(['username' => 'other', 'password' => 'mine-long-enough'], $this->lastBody());
        $this->assertArrayNotHasKey('password', $md->listUsers('m1')[0]);
        $md->deleteUser('m1', 'u1');
        $this->assertEquals('/managed-databases/m1/users/u1', $this->lastRequest()->getUri()->getPath());
    }
}
