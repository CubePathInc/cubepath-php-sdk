<?php

namespace Cubepath\Tests;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Middleware;
use Cubepath\CubepathClient;

class ObjectStorageServiceTest extends TestCase
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

    private function lastBody(): array
    {
        return json_decode((string) $this->lastRequest()->getBody(), true) ?? [];
    }

    public function testListTiers(): void
    {
        $client = $this->createClient([new Response(200, [], '[{"slug":"infrequent_access","accepting_new":true}]')]);
        $tiers = $client->objectStorage()->listTiers();

        $this->assertEquals('/object-storage/tiers', $this->lastRequest()->getUri()->getPath());
        $this->assertEquals('infrequent_access', $tiers[0]['slug']);
    }

    public function testListBucketsWithFilters(): void
    {
        $client = $this->createClient([new Response(200, [], '[{"uuid":"b1","name":"photos"}]')]);
        $buckets = $client->objectStorage()->listBuckets(['project_id' => 12, 'tier' => 'infrequent_access']);

        $uri = $this->lastRequest()->getUri();
        $this->assertEquals('/object-storage/buckets', $uri->getPath());
        $this->assertEquals('project_id=12&tier=infrequent_access', $uri->getQuery());
        $this->assertEquals('photos', $buckets[0]['name']);
    }

    public function testCreateBucket(): void
    {
        $client = $this->createClient([new Response(201, [], '{"uuid":"b1","status":"pending"}')]);
        $bucket = $client->objectStorage()->createBucket(['name' => 'photos', 'tier' => 'infrequent_access', 'project_id' => 12]);

        $this->assertEquals('POST', $this->lastRequest()->getMethod());
        $this->assertEquals('/object-storage/buckets', $this->lastRequest()->getUri()->getPath());
        $this->assertEquals(['name' => 'photos', 'tier' => 'infrequent_access', 'project_id' => 12], $this->lastBody());
        $this->assertEquals('pending', $bucket['status']);
    }

    public function testGetUpdateAndDeleteBucket(): void
    {
        $client = $this->createClient([
            new Response(200, [], '{"uuid":"b1","cdn":null}'),
            new Response(200, [], '{"detail":"Bucket updated"}'),
            new Response(200, [], '{"detail":"Bucket deletion started"}'),
            new Response(200, [], '{"detail":"Bucket deletion started"}'),
        ]);
        $os = $client->objectStorage();

        $this->assertNull($os->getBucket('b1')['cdn']);
        $this->assertEquals('/object-storage/buckets/b1', $this->lastRequest()->getUri()->getPath());

        $os->updateBucket('b1', ['versioning' => 'enabled']);
        $this->assertEquals('PATCH', $this->lastRequest()->getMethod());
        $this->assertEquals(['versioning' => 'enabled'], $this->lastBody());

        $os->deleteBucket('b1', true);
        $this->assertEquals('DELETE', $this->lastRequest()->getMethod());
        $this->assertEquals('force=true', $this->lastRequest()->getUri()->getQuery());

        $os->deleteBucket('b1');
        $this->assertEquals('', $this->lastRequest()->getUri()->getQuery());
    }

    public function testObjectLock(): void
    {
        $client = $this->createClient([
            new Response(201, [], '{"uuid":"b1","object_lock":{"enabled":true,"default_retention":{"mode":"governance","days":30,"years":null}}}'),
            new Response(200, [], '{"detail":"Bucket updated"}'),
            new Response(200, [], '{"detail":"Bucket updated"}'),
            new Response(200, [], '{"detail":"Bucket deletion started"}'),
            new Response(201, [], '{"uuid":"k1","bypass_governance":true}'),
        ]);
        $os = $client->objectStorage();

        $bucket = $os->createBucket([
            'name' => 'vault',
            'tier' => 'infrequent_access',
            'versioning' => false,
            'object_lock' => true,
            'object_lock_default' => ['mode' => 'governance', 'days' => 30],
            'accept_object_lock_terms' => true,
        ]);
        $this->assertEquals([
            'name' => 'vault',
            'tier' => 'infrequent_access',
            'object_lock' => true,
            'object_lock_default' => ['mode' => 'governance', 'days' => 30],
            'accept_object_lock_terms' => true,
        ], $this->lastBody());
        $this->assertTrue($bucket['object_lock']['enabled']);

        $os->setBucketObjectLock('b1', ['mode' => 'compliance', 'years' => 1], true);
        $this->assertEquals('PUT', $this->lastRequest()->getMethod());
        $this->assertEquals('/object-storage/buckets/b1/object-lock', $this->lastRequest()->getUri()->getPath());
        $this->assertEquals(['default_retention' => ['mode' => 'compliance', 'years' => 1], 'accept_object_lock_terms' => true], $this->lastBody());

        $os->setBucketObjectLock('b1', null);
        $this->assertSame('{"default_retention":null,"accept_object_lock_terms":false}', (string) $this->lastRequest()->getBody());

        $os->deleteBucket('b1', true, true);
        $this->assertEquals('force=true&bypass_governance=true', $this->lastRequest()->getUri()->getQuery());

        $key = $os->createKey(['name' => 'veeam', 'tier' => 'ia', 'permission' => 'read_write', 'bypass_governance' => true]);
        $this->assertTrue($this->lastBody()['bypass_governance']);
        $this->assertTrue($key['bypass_governance']);
    }

    public function testKeys(): void
    {
        $client = $this->createClient([
            new Response(201, [], '{"uuid":"k1","access_key_id":"CPABC","secret_access_key":"s3cr3t"}'),
            new Response(200, [], '[{"uuid":"k1","access_key_id":"CPABC"}]'),
            new Response(200, [], '{"detail":"Access key deletion started"}'),
        ]);
        $os = $client->objectStorage();

        $key = $os->createKey(['name' => 'web', 'tier' => 'ia', 'permission' => 'read_only', 'bucket_uuids' => ['b1']]);
        $this->assertEquals('/object-storage/keys', $this->lastRequest()->getUri()->getPath());
        $this->assertEquals(['b1'], $this->lastBody()['bucket_uuids']);
        $this->assertEquals('s3cr3t', $key['secret_access_key']);

        $keys = $os->listKeys();
        $this->assertArrayNotHasKey('secret_access_key', $keys[0]);

        $os->deleteKey('k1');
        $this->assertEquals('DELETE', $this->lastRequest()->getMethod());
        $this->assertEquals('/object-storage/keys/k1', $this->lastRequest()->getUri()->getPath());
    }

    public function testUsage(): void
    {
        $client = $this->createClient([new Response(200, [], '{"period":"2026-09","available_months":["2026-09"]}')]);
        $usage = $client->objectStorage()->getUsage(['period' => '2026-09']);

        $this->assertEquals('/object-storage/usage', $this->lastRequest()->getUri()->getPath());
        $this->assertEquals('period=2026-09', $this->lastRequest()->getUri()->getQuery());
        $this->assertEquals(['2026-09'], $usage['available_months']);
    }

    public function testBucketMetricsViaGraphql(): void
    {
        $part = '{"start":1,"end":2,"step":300,"series":[]}';
        $client = $this->createClient([new Response(200, [], '{"data":{"objectStorageBucket":{"uuid":"b1","name":"photos","storageMeasuredAt":1,"storage":' . $part . ',"traffic":' . $part . ',"responses":' . $part . '}}}')]);
        $metrics = $client->objectStorage()->bucketMetrics('b1', 'D7');

        $this->assertEquals('/graphql', $this->lastRequest()->getUri()->getPath());
        $this->assertEquals(['uuid' => 'b1', 'range' => 'D7'], $this->lastBody()['variables']);
        $this->assertStringContainsString('objectStorageBucket(uuid: $uuid)', $this->lastBody()['query']);
        $this->assertStringContainsString('responses(range: $range) { start end step', $this->lastBody()['query']);
        $this->assertEquals('photos', $metrics['name']);
    }

    public function testBucketMetricsNotFound(): void
    {
        $client = $this->createClient([new Response(200, [], '{"data":{"objectStorageBucket":null},"errors":[{"message":"Resource not found.","extensions":{"code":"NOT_FOUND"}}]}')]);
        try {
            $client->objectStorage()->bucketMetrics('nope');
            $this->fail('expected APIError');
        } catch (\Cubepath\APIError $e) {
            $this->assertEquals(404, $e->getStatusCode());
        }
    }

    public function testCreateBucketOriginSendsOnlyAllowedFields(): void
    {
        $client = $this->createClient([new Response(201, [], '{"uuid":"o1","object_storage_bucket_uuid":"b1"}')]);
        $origin = $client->cdn()->createBucketOrigin('z1', 'b1', 'assets', [
            'weight' => 100,
            'verify_ssl' => true,
            'health_check_enabled' => true,
        ]);

        $this->assertEquals('/cdn/zones/z1/origins', $this->lastRequest()->getUri()->getPath());
        $this->assertEquals(
            ['weight' => 100, 'name' => 'assets', 'object_storage_bucket_uuid' => 'b1'],
            $this->lastBody()
        );
        $this->assertEquals('b1', $origin['object_storage_bucket_uuid']);
    }

    public function testBucketTags(): void
    {
        $client = $this->createClient([
            new Response(200, [], '[{"uuid":"b1","tags":{"env":"prod"}}]'),
            new Response(201, [], '{"uuid":"b1","status":"pending","tags":{"env":"prod"}}'),
            new Response(200, [], '{"detail":"Bucket updated"}'),
            new Response(200, [], '{"detail":"Bucket updated"}'),
            new Response(200, [], '{"detail":"Bucket updated"}'),
        ]);
        $os = $client->objectStorage();

        $buckets = $os->listBuckets(['project_id' => 12, 'tags' => ['env=prod', 'team']]);
        $this->assertEquals('project_id=12&tag=env%3Dprod&tag=team', $this->lastRequest()->getUri()->getQuery());
        $this->assertEquals(['env' => 'prod'], $buckets[0]['tags']);

        $created = $os->createBucket(['name' => 'photos', 'tier' => 'infrequent_access', 'tags' => ['env' => 'prod']]);
        $this->assertEquals(['env' => 'prod'], $this->lastBody()['tags']);
        $this->assertEquals(['env' => 'prod'], $created['tags']);

        $os->updateBucket('b1', ['tags' => ['env' => 'dev', 'team' => '']]);
        $this->assertEquals('{"tags":{"env":"dev","team":""}}', (string) $this->lastRequest()->getBody());

        // An empty array clears every tag and must go out as a JSON object.
        $os->updateBucket('b1', ['tags' => []]);
        $this->assertEquals('{"tags":{}}', (string) $this->lastRequest()->getBody());

        $os->updateBucket('b1', ['protected' => true]);
        $this->assertArrayNotHasKey('tags', $this->lastBody());
    }

    public function testUsageTagFilter(): void
    {
        $client = $this->createClient([new Response(200, [], '{"buckets":[{"uuid":"b1","tags":{"env":"prod"}}]}')]);
        $usage = $client->objectStorage()->getUsage(['period' => '2026-09', 'tags' => ['env=prod']]);

        $this->assertEquals('/object-storage/usage', $this->lastRequest()->getUri()->getPath());
        $this->assertEquals('period=2026-09&tag=env%3Dprod', $this->lastRequest()->getUri()->getQuery());
        $this->assertEquals(['env' => 'prod'], $usage['buckets'][0]['tags']);
    }

    public function testBucketLifecycle(): void
    {
        $client = $this->createClient([
            new Response(200, [], '{"bucket_uuid":"b1","status":"active","generation":2,"applied_generation":2,"rules":[{"id":"logs-30d","enabled":true}]}'),
            new Response(202, [], '{"detail":"Lifecycle rules are being applied","generation":3,"notes":[]}'),
            new Response(202, [], '{"detail":"Lifecycle rules are being removed","generation":4}'),
        ]);
        $lifecycle = $client->objectStorage()->getBucketLifecycle('b1');
        $this->assertEquals('GET', $this->lastRequest()->getMethod());
        $this->assertEquals('/object-storage/buckets/b1/lifecycle', $this->lastRequest()->getUri()->getPath());
        $this->assertEquals('logs-30d', $lifecycle['rules'][0]['id']);

        $rules = [['id' => 'logs-30d', 'enabled' => true, 'filter' => ['prefix' => 'logs/'], 'expiration' => ['days' => 30]]];
        $change = $client->objectStorage()->putBucketLifecycle('b1', $rules);
        $this->assertEquals('PUT', $this->lastRequest()->getMethod());
        $this->assertEquals(['rules' => $rules], $this->lastBody());
        $this->assertEquals(3, $change['generation']);

        $change = $client->objectStorage()->deleteBucketLifecycle('b1');
        $this->assertEquals('DELETE', $this->lastRequest()->getMethod());
        $this->assertEquals('/object-storage/buckets/b1/lifecycle', $this->lastRequest()->getUri()->getPath());
        $this->assertEquals(4, $change['generation']);
    }

    public function testListAndGetReplications(): void
    {
        $client = $this->createClient([
            new Response(200, [], '[{"uuid":"r1","direction":"outgoing","destination":{"type":"cubepath"}}]'),
            new Response(200, [], '[]'),
            new Response(200, [], '{"uuid":"r1","status":"active","metrics":{"queued_objects":0}}'),
        ]);
        $os = $client->objectStorage();

        $list = $os->listReplications(['direction' => 'outgoing', 'bucket_uuid' => 'b1']);
        $this->assertEquals('GET', $this->lastRequest()->getMethod());
        $this->assertEquals('/object-storage/replications', $this->lastRequest()->getUri()->getPath());
        $this->assertEquals('direction=outgoing&bucket_uuid=b1', $this->lastRequest()->getUri()->getQuery());
        $this->assertEquals('r1', $list[0]['uuid']);

        $os->listReplications();
        $this->assertEquals('', $this->lastRequest()->getUri()->getQuery());

        $detail = $os->getReplication('r1');
        $this->assertEquals('/object-storage/replications/r1', $this->lastRequest()->getUri()->getPath());
        $this->assertEquals(0, $detail['metrics']['queued_objects']);
    }

    public function testCreateReplication(): void
    {
        $client = $this->createClient([
            new Response(201, [], '{"detail":"Replication is being configured","uuid":"r1","status":"pending"}'),
            new Response(201, [], '{"detail":"Replication is being configured","uuid":"r2","status":"pending"}'),
        ]);
        $os = $client->objectStorage();

        $created = $os->createReplication([
            'source_bucket_uuid' => 'b1',
            'destination' => ['type' => 'cubepath', 'bucket_uuid' => 'b2', 'grant_token' => 'cprg_abc'],
            'prefix' => 'img/',
        ]);
        $this->assertEquals('POST', $this->lastRequest()->getMethod());
        $this->assertEquals('/object-storage/replications', $this->lastRequest()->getUri()->getPath());
        $this->assertEquals([
            'source_bucket_uuid' => 'b1',
            'destination' => ['type' => 'cubepath', 'bucket_uuid' => 'b2', 'grant_token' => 'cprg_abc'],
            'prefix' => 'img/',
        ], $this->lastBody());
        $this->assertEquals('pending', $created['status']);

        $external = [
            'type' => 'external', 'provider' => 'aws', 'endpoint' => 's3.eu-west-1.amazonaws.com',
            'region' => 'eu-west-1', 'bucket' => 'acme-backup', 'access_key_id' => 'AKIA',
            'secret_access_key' => 'secret',
        ];
        $os->createReplication([
            'source_bucket_uuid' => 'b1',
            'destination' => $external,
            'tags' => [['key' => 'backup', 'value' => 'yes']],
            'existing_objects' => false,
        ]);
        $body = $this->lastBody();
        $this->assertEquals($external, $body['destination']);
        $this->assertEquals([['key' => 'backup', 'value' => 'yes']], $body['tags']);
        $this->assertFalse($body['existing_objects']);
    }

    public function testUpdateReplicationSendsExplicitNulls(): void
    {
        $client = $this->createClient([
            new Response(200, [], '{"detail":"Replication updated"}'),
            new Response(200, [], '{"detail":"Replication updated"}'),
        ]);
        $os = $client->objectStorage();

        $os->updateReplication('r1', ['prefix' => null, 'tags' => [['key' => 'k', 'value' => 'v']]]);
        $this->assertEquals('PATCH', $this->lastRequest()->getMethod());
        $this->assertEquals('/object-storage/replications/r1', $this->lastRequest()->getUri()->getPath());
        $this->assertEquals('{"prefix":null,"tags":[{"key":"k","value":"v"}]}', (string) $this->lastRequest()->getBody());

        $os->updateReplication('r1', ['enabled' => false, 'destination' => ['access_key_id' => 'AK', 'secret_access_key' => 'SK']]);
        $this->assertEquals(
            ['enabled' => false, 'destination' => ['access_key_id' => 'AK', 'secret_access_key' => 'SK']],
            $this->lastBody()
        );
    }

    public function testDeleteResyncAndRevokeReplication(): void
    {
        $client = $this->createClient([
            new Response(200, [], '{"detail":"Replication removal started"}'),
            new Response(202, [], '{"detail":"Resync queued"}'),
            new Response(202, [], '{"detail":"Resync queued"}'),
            new Response(200, [], '{"detail":"Incoming replication revoked"}'),
        ]);
        $os = $client->objectStorage();

        $os->deleteReplication('r1');
        $this->assertEquals('DELETE', $this->lastRequest()->getMethod());
        $this->assertEquals('/object-storage/replications/r1', $this->lastRequest()->getUri()->getPath());

        $result = $os->resyncReplication('r1', 30);
        $this->assertEquals('POST', $this->lastRequest()->getMethod());
        $this->assertEquals('/object-storage/replications/r1/resync', $this->lastRequest()->getUri()->getPath());
        $this->assertEquals('{"older_than_days":30}', (string) $this->lastRequest()->getBody());
        $this->assertEquals('Resync queued', $result['detail']);

        $os->resyncReplication('r1');
        $this->assertEquals('{"older_than_days":null}', (string) $this->lastRequest()->getBody());

        $os->revokeReplication('r1');
        $this->assertEquals('POST', $this->lastRequest()->getMethod());
        $this->assertEquals('/object-storage/replications/r1/revoke', $this->lastRequest()->getUri()->getPath());
    }

    public function testReplicationGrants(): void
    {
        $client = $this->createClient([
            new Response(201, [], '{"detail":"Replication grant created","uuid":"g1","token":"cprg_abc","token_prefix":"cprg_abcd","bucket_uuid":"b2","note":"for Acme","expires_at":"2026-10-09T10:00:00"}'),
            new Response(201, [], '{"uuid":"g2","token":"cprg_def"}'),
            new Response(200, [], '[{"uuid":"g1","status":"open","token_prefix":"cprg_abcd"}]'),
            new Response(200, [], '{"detail":"Replication grant revoked"}'),
        ]);
        $os = $client->objectStorage();

        $grant = $os->createReplicationGrant('b2', 'for Acme', 14);
        $this->assertEquals('POST', $this->lastRequest()->getMethod());
        $this->assertEquals('/object-storage/buckets/b2/replication-grants', $this->lastRequest()->getUri()->getPath());
        $this->assertEquals(['note' => 'for Acme', 'expires_in_days' => 14], $this->lastBody());
        $this->assertEquals('cprg_abc', $grant['token']);

        $os->createReplicationGrant('b2');
        $this->assertEquals('', (string) $this->lastRequest()->getBody());

        $grants = $os->listReplicationGrants('b2');
        $this->assertEquals('GET', $this->lastRequest()->getMethod());
        $this->assertEquals('/object-storage/buckets/b2/replication-grants', $this->lastRequest()->getUri()->getPath());
        $this->assertEquals('open', $grants[0]['status']);

        $os->deleteReplicationGrant('g1');
        $this->assertEquals('DELETE', $this->lastRequest()->getMethod());
        $this->assertEquals('/object-storage/replication-grants/g1', $this->lastRequest()->getUri()->getPath());
    }
}
