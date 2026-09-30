<?php

namespace Cubepath\Tests;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Middleware;
use Cubepath\CubepathClient;

class CloudAlertAndTranscoderServiceTest extends TestCase
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

    public function testAlerts(): void
    {
        $client = $this->createClient([
            new Response(200, [], '[{"id":"t1"}]'),
            new Response(201, [], '{"id":"t1","status":"enabled"}'),
            new Response(200, [], '{"id":"t1","actions":[]}'),
            new Response(200, [], '{"id":"t1","status":"disabled"}'),
            new Response(200, [], '[]'),
            new Response(204, [], ''),
        ]);
        $a = $client->cloudAlerts();

        $a->list(['project_id' => 3, 'status' => 'enabled']);
        $this->assertEquals('/triggers/', $this->lastRequest()->getUri()->getPath());
        $this->assertEquals('project_id=3&status=enabled', $this->lastRequest()->getUri()->getQuery());

        $params = [
            'project_id' => 3, 'name' => 'cpu', 'target_type' => 'vps', 'target_id' => '10',
            'metric_type' => 'cpu', 'operator' => 'gt', 'threshold' => 90,
            'actions' => [['action_type' => 'notify', 'notificator_id' => 'n1']],
        ];
        $this->assertEquals('enabled', $a->create($params)['status']);
        $this->assertEquals('POST', $this->lastRequest()->getMethod());
        $this->assertEquals($params, $this->lastBody());

        $a->get('t1');
        $this->assertEquals('/triggers/t1', $this->lastRequest()->getUri()->getPath());

        $a->update('t1', ['status' => 'disabled']);
        $this->assertEquals('PUT', $this->lastRequest()->getMethod());
        $this->assertEquals(['status' => 'disabled'], $this->lastBody());

        $a->history('t1', 10);
        $this->assertEquals('/triggers/t1/history', $this->lastRequest()->getUri()->getPath());
        $this->assertEquals('limit=10', $this->lastRequest()->getUri()->getQuery());

        $this->assertEquals([], $a->delete('t1'));
        $this->assertEquals('DELETE', $this->lastRequest()->getMethod());
    }

    public function testNotificators(): void
    {
        $client = $this->createClient([
            new Response(200, [], '[]'),
            new Response(201, [], '{"id":"n1","type":"email"}'),
            new Response(200, [], '{"id":"n1"}'),
            new Response(200, [], '{"id":"n1","enabled":false}'),
            new Response(204, [], ''),
        ]);
        $a = $client->cloudAlerts();

        $a->listNotificators();
        $this->assertEquals('/triggers/notificators/', $this->lastRequest()->getUri()->getPath());

        $a->createNotificator(['name' => 'ops', 'type' => 'email', 'config' => []]);
        $this->assertEquals('email', $this->lastBody()['type']);

        $a->getNotificator('n1');
        $this->assertEquals('/triggers/notificators/n1', $this->lastRequest()->getUri()->getPath());

        $a->updateNotificator('n1', ['enabled' => false]);
        $this->assertEquals('PUT', $this->lastRequest()->getMethod());

        $a->deleteNotificator('n1');
        $this->assertEquals('DELETE', $this->lastRequest()->getMethod());
        $this->assertEquals('/triggers/notificators/n1', $this->lastRequest()->getUri()->getPath());
    }

    public function testTranscoderJobs(): void
    {
        $client = $this->createClient([
            new Response(201, [], '{"uuid":"j1","status":"queued"}'),
            new Response(201, [], '{"batch_id":"b1","job_ids":["j2"],"count":1}'),
            new Response(200, [], '{"jobs":[{"uuid":"j1"}],"limit":10,"offset":0}'),
            new Response(200, [], '{"uuid":"j1","status":"encoding"}'),
            new Response(200, [], '{"outputs":[],"destination":{}}'),
            new Response(200, [], '{"detail":"Job canceled","status":"canceled"}'),
        ]);
        $t = $client->transcoder();

        $job = $t->createJob([
            'input' => ['source' => 'url', 'url' => 'https://example.com/in.mp4'],
            'output' => ['s3' => ['bucket' => 'out']],
            'outputs' => [['type' => 'thumbnails']],
        ]);
        $this->assertEquals('/transcoder/jobs', $this->lastRequest()->getUri()->getPath());
        $this->assertEquals('thumbnails', $this->lastBody()['outputs'][0]['type']);
        $this->assertEquals('queued', $job['status']);

        $this->assertEquals(1, $t->createBatch(['output' => [], 'outputs' => [], 'inputs' => []])['count']);
        $this->assertEquals('/transcoder/jobs/batch', $this->lastRequest()->getUri()->getPath());

        $t->listJobs(['batch_id' => 'b1', 'limit' => 10]);
        $this->assertEquals('batch_id=b1&limit=10', $this->lastRequest()->getUri()->getQuery());

        $t->getJob('j1');
        $this->assertEquals('/transcoder/jobs/j1', $this->lastRequest()->getUri()->getPath());

        $t->getJobOutputs('j1');
        $this->assertEquals('/transcoder/jobs/j1/outputs', $this->lastRequest()->getUri()->getPath());

        $this->assertEquals('canceled', $t->cancelJob('j1')['status']);
        $this->assertEquals('DELETE', $this->lastRequest()->getMethod());
    }

    public function testNewServicesInitialize(): void
    {
        $client = $this->createClient([]);

        $this->assertInstanceOf(\Cubepath\Services\ManagedDatabaseService::class, $client->managedDatabases());
        $this->assertInstanceOf(\Cubepath\Services\DDoSMitigationService::class, $client->ddosMitigation());
        $this->assertInstanceOf(\Cubepath\Services\CloudAlertService::class, $client->cloudAlerts());
        $this->assertInstanceOf(\Cubepath\Services\TranscoderService::class, $client->transcoder());
        $this->assertInstanceOf(\Cubepath\Services\AvailabilityGroupService::class, $client->vps()->availabilityGroups());
        $this->assertSame($client->transcoder(), $client->transcoder());
    }
}
