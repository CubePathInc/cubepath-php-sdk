<?php

namespace Cubepath\Tests;

use Cubepath\BaremetalRef;
use Cubepath\CubepathClient;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * A baremetal server is named by its uuid: the API returns it as "id" (and "uuid") and takes
 * nothing else. A server number is refused before any request.
 */
class BaremetalReferenceTest extends TestCase
{
    private const UUID = '3f2a8c10-1b2c-4d3e-8f4a-5b6c7d8e9f01';

    private array $history = [];

    /** @param Response[] $responses */
    private function client(array $responses): CubepathClient
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new CubepathClient('test-token-123', [
            'http_client' => new HttpClient(['handler' => $stack]),
            'max_retries' => 0,
            'rate_limit_per_sec' => 1000,
        ]);
    }

    private function ok(int $n): array
    {
        return array_fill(0, $n, new Response(200, [], '{"detail":"ok"}'));
    }

    private function path(int $i = -1): string
    {
        $entry = $i < 0 ? end($this->history) : $this->history[$i];
        return $entry['request']->getUri()->getPath();
    }

    private function projects(array $baremetals): Response
    {
        return new Response(200, [], json_encode([['project' => ['id' => 1], 'baremetals' => $baremetals]]));
    }

    /** @return array<string, array{0:string,1:string}> */
    public function validRefs(): array
    {
        return [
            'uuid' => [self::UUID, self::UUID],
            'uuid upper case' => [strtoupper(self::UUID), self::UUID],
        ];
    }

    /**
     * @dataProvider validRefs
     */
    public function testEveryMethodPutsTheUuidInTheUrl(string $input, string $expected): void
    {
        $bm = $this->client($this->ok(17))->baremetal();

        $bm->listOS($input);
        $this->assertSame("/baremetal/os/{$expected}", $this->path());
        $bm->kvm($input);
        $this->assertSame("/baremetal/{$expected}/kvm", $this->path());
        $bm->protection($input, true);
        $this->assertSame("/baremetal/{$expected}/protection", $this->path());
        $bm->moveToProject($input, 3);
        $this->assertSame("/baremetal/{$expected}/move-project", $this->path());
        $bm->addSSHKeys($input, [5]);
        $this->assertSame("/baremetal/{$expected}/ssh-keys", $this->path());
        $bm->removeSSHKey($input, 5);
        $this->assertSame("/baremetal/{$expected}/ssh-keys/5", $this->path());
        $bm->attachNetwork($input, 7);
        $this->assertSame("/baremetal/{$expected}/network", $this->path());
        $bm->detachNetwork($input);
        $this->assertSame("/baremetal/{$expected}/network", $this->path());
        $bm->update($input, ['label' => 'x']);
        $this->assertSame("/baremetal/update/{$expected}", $this->path());
        $bm->power($input, 'reboot');
        $this->assertSame("/baremetal/{$expected}/power/reboot", $this->path());
        $bm->rescue($input);
        $this->assertSame("/baremetal/{$expected}/rescue", $this->path());
        $bm->resetBMC($input);
        $this->assertSame("/baremetal/{$expected}/reset-bmc", $this->path());
        $bm->ipmiSession($input);
        $this->assertSame("/ipmi-proxy/create-session/{$expected}", $this->path());
        $bm->reinstall($input, ['os_name' => 'debian-12']);
        $this->assertSame("/baremetal/{$expected}/reinstall", $this->path());
        $bm->cancelReinstall($input);
        $this->assertSame("/baremetal/{$expected}/reinstall", $this->path());
        $bm->monitoringEnable($input);
        $this->assertSame("/baremetal/{$expected}/monitoring", $this->path());
        $bm->monitoringDisable($input);
        $this->assertSame("/baremetal/{$expected}/monitoring", $this->path());
    }

    /** @return array<string, array{0:mixed}> */
    public function invalidRefs(): array
    {
        return [
            'empty' => [''],
            'server number' => ['5'],
            'server number as int' => [5],
            'zero padded number' => ['005'],
            'text' => ['abc'],
            'uuid prefix and tail' => [self::UUID . 'x'],
            'uuid without hyphens' => [str_replace('-', '', self::UUID)],
            'uuid with newline' => [self::UUID . "\n"],
            'uuid with space' => [' ' . self::UUID],
            'path traversal' => ['5/../6'],
            'query' => ['5?x=1'],
        ];
    }

    /**
     * @dataProvider invalidRefs
     * @param mixed $input
     */
    public function testInvalidReferenceIsRejectedBeforeAnyRequest($input): void
    {
        $bm = $this->client($this->ok(1))->baremetal();
        try {
            $bm->power($input, 'reboot');
            $this->fail('expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('must be a uuid', $e->getMessage());
        }
        $this->assertCount(0, $this->history);
    }

    public function testGetAndReinstallStatusAndBmcSensorsRejectANumber(): void
    {
        $bm = $this->client($this->ok(1))->baremetal();
        foreach (['get', 'reinstallStatus', 'bmcSensors'] as $method) {
            try {
                $bm->$method('9');
                $this->fail("{$method} accepted a server number");
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('must be a uuid', $e->getMessage());
            }
        }
        $this->assertCount(0, $this->history);
    }

    public function testGetByUuidAnyCase(): void
    {
        $other = '11111111-1111-4111-8111-111111111111';
        $list = [
            ['id' => $other, 'uuid' => $other, 'hostname' => 'a'],
            ['id' => self::UUID, 'uuid' => self::UUID, 'hostname' => 'b'],
        ];
        $bm = $this->client([$this->projects($list), $this->projects($list)])->baremetal();
        $this->assertSame('b', $bm->get(self::UUID)['hostname']);
        $this->assertSame('b', $bm->get(strtoupper(self::UUID))['hostname']);
    }

    public function testGetNotFoundNamesTheUuid(): void
    {
        $bm = $this->client([$this->projects([])])->baremetal();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Baremetal server ' . self::UUID . ' not found');
        $bm->get(self::UUID);
    }

    public function testReinstallStatusByUuid(): void
    {
        $bm = $this->client([$this->projects([['id' => self::UUID, 'uuid' => self::UUID, 'status' => 'deploying']])])->baremetal();
        $st = $bm->reinstallStatus(self::UUID);
        $this->assertTrue($st['is_reinstalling']);
    }

    /** @dataProvider validRefs */
    public function testBmcSensorsSendsTheUuid(string $input, string $expected): void
    {
        $body = '{"data":{"baremetal":{"sensors":{"ipmiAvailable":true,"powerOn":true,"lastSeen":1,"temperatures":[],"fans":[]}}}}';
        $bm = $this->client([new Response(200, [], $body)])->baremetal();
        $bm->bmcSensors($input);
        $sent = json_decode((string) end($this->history)['request']->getBody(), true);
        $this->assertSame($expected, $sent['variables']['id']);
    }

    public function testBmcSensorsNotFoundNamesTheReference(): void
    {
        $bm = $this->client([new Response(200, [], '{"data":{"baremetal":null}}')])->baremetal();
        try {
            $bm->bmcSensors(self::UUID);
            $this->fail('expected APIError');
        } catch (\Cubepath\APIError $e) {
            $this->assertSame(404, $e->getCode());
            $this->assertStringContainsString(self::UUID, $e->getMessage());
        }
    }

    public function testFloatingIPAssignBaremetalByUuid(): void
    {
        $fip = $this->client($this->ok(1))->floatingIPs();
        $fip->assign('baremetal', strtoupper(self::UUID), '1.2.3.4');
        $this->assertSame('/floating_ips/assign/baremetal/' . self::UUID, $this->path());
        $this->assertSame('address=1.2.3.4', end($this->history)['request']->getUri()->getQuery());
    }

    public function testFloatingIPAssignBaremetalRejectsANumber(): void
    {
        $fip = $this->client($this->ok(1))->floatingIPs();
        foreach ([7, '7', 'abc'] as $bad) {
            try {
                $fip->assign('baremetal', $bad, '1.2.3.4');
                $this->fail('a server number is not a baremetal reference');
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('must be a uuid', $e->getMessage());
            }
        }
        $this->assertCount(0, $this->history);
    }

    public function testFloatingIPAssignVpsKeepsTheIntegerRule(): void
    {
        $fip = $this->client($this->ok(2))->floatingIPs();
        $fip->assign('vps', 10, '1.2.3.4');
        $this->assertSame('/floating_ips/assign/vps/10', $this->path());
        $fip->assign('vps', '10', '1.2.3.4');
        $this->assertSame('/floating_ips/assign/vps/10', $this->path());
    }

    public function testFloatingIPAssignVpsRejectsAUuid(): void
    {
        $fip = $this->client($this->ok(1))->floatingIPs();
        try {
            $fip->assign('vps', self::UUID, '1.2.3.4');
            $this->fail('a uuid is not a VPS id');
        } catch (\InvalidArgumentException $e) {
            $this->assertCount(0, $this->history);
        }
    }

    public function testBaremetalTargetsTakeTheUuid(): void
    {
        $c = $this->client($this->ok(5));
        $upper = strtoupper(self::UUID);
        $c->networks()->createRoute(5, ['destination' => '10.1.0.0/24', 'next_hop_type' => 'baremetal', 'next_hop_target' => $upper]);
        $c->networks()->createBGPPeer(5, ['peer_type' => 'baremetal', 'peer_target' => $upper, 'remote_asn' => 65001]);
        $c->loadBalancer()->addTarget('lb', 'l', ['target_type' => 'baremetal', 'target_uuid' => $upper, 'weight' => 100]);
        $c->loadBalancer()->addTargets('lb', 'l', [['target_type' => 'vps', 'target_uuid' => '7']]);
        $c->cloudAlerts()->create(['project_id' => 1, 'name' => 'a', 'target_type' => 'baremetal', 'target_id' => $upper]);
        $bodies = array_map(function ($h) {
            return json_decode((string) $h['request']->getBody(), true);
        }, $this->history);
        $this->assertSame(self::UUID, $bodies[0]['next_hop_target']);
        $this->assertSame(self::UUID, $bodies[1]['peer_target']);
        $this->assertSame(self::UUID, $bodies[2]['target_uuid']);
        $this->assertSame('7', $bodies[3]['targets'][0]['target_uuid']);
        $this->assertSame(self::UUID, $bodies[4]['target_id']);
    }

    public function testBaremetalTargetsRejectANumberBeforeAnyRequest(): void
    {
        $c = $this->client($this->ok(1));
        $calls = [
            function () use ($c) {
                $c->networks()->createRoute(5, ['destination' => '10.1.0.0/24', 'next_hop_type' => 'baremetal', 'next_hop_target' => '12']);
            },
            function () use ($c) {
                $c->networks()->createBGPPeer(5, ['peer_type' => 'baremetal', 'peer_target' => '12', 'remote_asn' => 65001]);
            },
            function () use ($c) {
                $c->loadBalancer()->addTarget('lb', 'l', ['target_type' => 'baremetal', 'target_uuid' => '12', 'weight' => 100]);
            },
            function () use ($c) {
                $c->loadBalancer()->addTargets('lb', 'l', [['target_type' => 'baremetal', 'target_uuid' => '12']]);
            },
            function () use ($c) {
                $c->cloudAlerts()->update('a1', ['target_type' => 'baremetal', 'target_id' => '12']);
            },
        ];
        foreach ($calls as $call) {
            try {
                $call();
                $this->fail('a server number is not a baremetal target');
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('must be a uuid', $e->getMessage());
            }
        }
        $this->assertCount(0, $this->history);
    }

    public function testNormalizeAndMatchesHelpers(): void
    {
        $this->assertSame(self::UUID, BaremetalRef::normalize(strtoupper(self::UUID)));
        $this->assertTrue(BaremetalRef::matches(['id' => strtoupper(self::UUID)], self::UUID));
        $this->assertFalse(BaremetalRef::matches(['id' => 12], '12'));
        $this->assertFalse(BaremetalRef::matches(['uuid' => self::UUID], self::UUID));
        $this->assertSame('7', BaremetalRef::target('vps', '7'));
        $this->assertSame(self::UUID, BaremetalRef::target('baremetal', strtoupper(self::UUID)));
    }
}
