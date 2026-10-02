<?php

namespace Cubepath\Tests;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Middleware;
use Cubepath\CubepathClient;
use Cubepath\StorageEventSignatureException;
use Cubepath\Webhooks;

class ObjectStorageEventsTest extends TestCase
{
    private const SECRET = 'whsec_0123456789abcdefABCDEF0123456789';
    private const TS = '1790000000';
    private const BODY = '{"id":"evt_01","type":"object.created"}';
    private const SIG = '348e719a6c9fb75f1c6a60cc81ec5914a599eb648b1ab9d81e483fc2309c6f3b';
    private const PREV_SIG = 'd1cc88ad9e8ba98e234697ea257b0969a065c04f879a1c9cdcab90ef316a953d';
    private const NOW = 1790000000;

    private array $requestHistory = [];

    private function createClient(int $count): CubepathClient
    {
        $this->requestHistory = [];
        $responses = [new Response(201, [], '{"destination":{"uuid":"d1"},"signing_secret":"whsec_x"}')];
        for ($i = 1; $i < $count; $i++) {
            $responses[] = new Response(200, [], '{}');
        }
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->requestHistory));

        return new CubepathClient('test-token-123', [
            'http_client' => new HttpClient(['handler' => $stack]),
            'max_retries' => 0,
            'rate_limit_per_sec' => 1000,
        ]);
    }

    public function testEventRoutes(): void
    {
        $os = $this->createClient(12)->objectStorage();
        $created = $os->createEventDestination(['name' => 'hook', 'type' => 'webhook', 'url' => 'https://example.com/h']);
        $this->assertEquals('whsec_x', $created['signing_secret']);
        $os->listEventDestinations();
        $os->getEventDestination('d1');
        $os->updateEventDestination('d1', ['enabled' => false]);
        $os->rotateEventDestinationSecret('d1');
        $os->testEventDestination('d1');
        $os->listEventDeliveries('d1', ['status' => 'failed', 'limit' => 10, 'before' => 1790964001250]);
        $os->deleteEventDestination('d1');
        $os->listEventRules('b1');
        $os->createEventRule('b1', ['name' => 'r', 'destination_uuid' => 'd1', 'events' => ['object.created']]);
        $os->updateEventRule('b1', 'r1', ['enabled' => false]);
        $os->deleteEventRule('b1', 'r1');

        $routes = array_map(function ($h) {
            $uri = $h['request']->getUri();
            return $h['request']->getMethod() . ' ' . $uri->getPath() . ($uri->getQuery() ? '?' . $uri->getQuery() : '');
        }, $this->requestHistory);
        $this->assertEquals([
            'POST /object-storage/event-destinations',
            'GET /object-storage/event-destinations',
            'GET /object-storage/event-destinations/d1',
            'PATCH /object-storage/event-destinations/d1',
            'POST /object-storage/event-destinations/d1/rotate-secret',
            'POST /object-storage/event-destinations/d1/test',
            'GET /object-storage/event-destinations/d1/deliveries?status=failed&limit=10&before=1790964001250',
            'DELETE /object-storage/event-destinations/d1',
            'GET /object-storage/buckets/b1/event-rules',
            'POST /object-storage/buckets/b1/event-rules',
            'PATCH /object-storage/buckets/b1/event-rules/r1',
            'DELETE /object-storage/buckets/b1/event-rules/r1',
        ], $routes);
        $this->assertEquals(
            ['name' => 'r', 'destination_uuid' => 'd1', 'events' => ['object.created']],
            json_decode((string) $this->requestHistory[9]['request']->getBody(), true)
        );
    }

    public function testSignatureValid(): void
    {
        Webhooks::verifyStorageEventSignature(self::SECRET, self::TS, self::BODY, 'v1=' . self::SIG, 300, self::NOW);
        Webhooks::verifyStorageEventSignature(self::SECRET, self::TS, self::BODY, 'v1=' . self::PREV_SIG . ',v1=' . self::SIG, 300, self::NOW);
        Webhooks::verifyStorageEventSignature('whsec_previous', self::TS, self::BODY, 'v1=' . self::SIG . ', v1=' . self::PREV_SIG, 300, self::NOW);
        // The exact form the service sends during a rotation.
        Webhooks::verifyStorageEventSignature(self::SECRET, self::TS, self::BODY, 'v1=' . self::SIG . ', v1=' . self::PREV_SIG, 300, self::NOW);
        Webhooks::verifyStorageEventSignature(self::SECRET, self::TS, self::BODY, 'v1=' . self::SIG, 0, self::NOW + 3600);
        $this->addToAssertionCount(5);
    }

    public function invalidProvider(): array
    {
        return [
            'tampered body' => [self::TS, '{"id":"evt_02"}', 'v1=' . self::SIG, self::NOW],
            'wrong secret' => [self::TS, self::BODY, 'v1=' . self::PREV_SIG, self::NOW],
            'unknown scheme' => [self::TS, self::BODY, 'v0=' . self::SIG, self::NOW],
            'expired' => [self::TS, self::BODY, 'v1=' . self::SIG, self::NOW + 360],
            'future' => [self::TS, self::BODY, 'v1=' . self::SIG, self::NOW - 360],
            'bad timestamp' => ['abc', self::BODY, 'v1=' . self::SIG, self::NOW],
            'empty header' => [self::TS, self::BODY, '', self::NOW],
            'short value' => [self::TS, self::BODY, 'v1=abcd', self::NOW],
            'clock' => [self::TS, self::BODY, 'v1=' . self::SIG, null],
        ];
    }

    /**
     * @dataProvider invalidProvider
     */
    public function testSignatureRejected(string $ts, string $body, string $header, ?int $now): void
    {
        $this->expectException(StorageEventSignatureException::class);
        Webhooks::verifyStorageEventSignature(self::SECRET, $ts, $body, $header, 300, $now);
    }
}
