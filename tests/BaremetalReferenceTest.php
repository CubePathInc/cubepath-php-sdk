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
 * A baremetal server is named by its number or its uuid. The client must work against the API
 * of today (id integer, no uuid) and the one after the cutover (id integer plus uuid).
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

    /** @return array<string, array{0:mixed,1:string}> */
    public function validRefs(): array
    {
        return [
            'int' => [5, '5'],
            'digits' => ['5', '5'],
            'zero padded' => ['005', '005'],
            'uuid' => [self::UUID, self::UUID],
            'uuid upper case' => [strtoupper(self::UUID), self::UUID],
        ];
    }

    /**
     * @dataProvider validRefs
     * @param mixed $input
     */
    public function testEveryMethodPutsTheReferenceInTheUrl($input, string $expected): void
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

    public function testUuidStartingWithADigitIsNeverTurnedIntoServerThree(): void
    {
        $bm = $this->client($this->ok(1))->baremetal();
        $bm->power(self::UUID, 'start');
        $this->assertSame('/baremetal/' . self::UUID . '/power/start', $this->path());
        $this->assertStringNotContainsString('/baremetal/3/', $this->path());
    }

    /** @return array<string, array{0:mixed}> */
    public function invalidRefs(): array
    {
        return [
            'empty' => [''],
            'text' => ['abc'],
            'prefix digits' => ['3abc'],
            'uuid prefix and tail' => [self::UUID . 'x'],
            'uuid without hyphens' => [str_replace('-', '', self::UUID)],
            'uuid with newline' => [self::UUID . "\n"],
            'number with newline' => ["3\n"],
            'number with space' => [' 3'],
            'negative int' => [-1],
            'negative text' => ['-1'],
            'float' => [3.5],
            'null' => [null],
            'bool' => [true],
            'array' => [[3]],
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
            $this->assertStringContainsString('number or a uuid', $e->getMessage());
        }
        $this->assertCount(0, $this->history);
    }

    public function testGetAndReinstallStatusAndBmcSensorsRejectInvalidReferences(): void
    {
        $bm = $this->client($this->ok(1))->baremetal();
        foreach (['get', 'reinstallStatus', 'bmcSensors'] as $method) {
            try {
                $bm->$method('nope');
                $this->fail("{$method} accepted an invalid reference");
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('number or a uuid', $e->getMessage());
            }
        }
        $this->assertCount(0, $this->history);
    }

    public function testGetByNumberAgainstTheAPIOfToday(): void
    {
        $bm = $this->client([$this->projects([['id' => 4, 'hostname' => 'a'], ['id' => 5, 'hostname' => 'b']])])->baremetal();
        $this->assertSame('b', $bm->get(5)['hostname']);
    }

    public function testGetByNumberTextAgainstTheAPIOfToday(): void
    {
        $bm = $this->client([$this->projects([['id' => 5, 'hostname' => 'b']])])->baremetal();
        $this->assertSame('b', $bm->get('5')['hostname']);
    }

    public function testGetMatchesANumericStringId(): void
    {
        $bm = $this->client([$this->projects([['id' => '5', 'hostname' => 'b']])])->baremetal();
        $this->assertSame('b', $bm->get(5)['hostname']);
    }

    public function testGetByNumberAgainstThePostCutoverAPI(): void
    {
        $bm = $this->client([$this->projects([
            ['id' => 4, 'uuid' => '11111111-1111-4111-8111-111111111111', 'hostname' => 'a'],
            ['id' => 5, 'uuid' => self::UUID, 'hostname' => 'b'],
        ])])->baremetal();
        $this->assertSame('b', $bm->get(5)['hostname']);
    }

    public function testGetByUuidAnyCase(): void
    {
        $list = [
            ['id' => 4, 'uuid' => '11111111-1111-4111-8111-111111111111', 'hostname' => 'a'],
            ['id' => 5, 'uuid' => self::UUID, 'hostname' => 'b'],
        ];
        $bm = $this->client([$this->projects($list), $this->projects($list)])->baremetal();
        $this->assertSame('b', $bm->get(self::UUID)['hostname']);
        $this->assertSame('b', $bm->get(strtoupper(self::UUID))['hostname']);
    }

    public function testGetByUuidFindsNothingOnTheAPIOfToday(): void
    {
        $bm = $this->client([$this->projects([['id' => 3, 'hostname' => 'three']])])->baremetal();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not found');
        $bm->get(self::UUID);
    }

    public function testGetNeverMatchesAUuidThatStartsWithADigitToTheServerOfThatNumber(): void
    {
        $bm = $this->client([$this->projects([['id' => 3, 'uuid' => '99999999-1111-4111-8111-111111111111', 'hostname' => 'three']])])->baremetal();
        $this->expectException(\RuntimeException::class);
        $bm->get(self::UUID);
    }

    public function testGetIgnoresServersWithoutUuidOrId(): void
    {
        $bm = $this->client([$this->projects([['hostname' => 'x'], ['id' => 8, 'uuid' => null, 'hostname' => 'y']])])->baremetal();
        $this->assertSame('y', $bm->get(8)['hostname']);
    }

    public function testGetNotFoundNamesTheReference(): void
    {
        $bm = $this->client([$this->projects([])])->baremetal();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Baremetal server 9 not found');
        $bm->get(9);
    }

    public function testReinstallStatusByUuid(): void
    {
        $bm = $this->client([$this->projects([['id' => 5, 'uuid' => self::UUID, 'status' => 'deploying']])])->baremetal();
        $st = $bm->reinstallStatus(self::UUID);
        $this->assertTrue($st['is_reinstalling']);
    }

    /** @dataProvider validRefs
     *  @param mixed $input */
    public function testBmcSensorsSendsTheReferenceAsText($input, string $expected): void
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

    public function testFloatingIPAssignBaremetalByNumberAndUuid(): void
    {
        $fip = $this->client($this->ok(4))->floatingIPs();

        $fip->assign('baremetal', 7, '1.2.3.4');
        $this->assertSame('/floating_ips/assign/baremetal/7', $this->path());
        $fip->assign('baremetal', '7', '1.2.3.4');
        $this->assertSame('/floating_ips/assign/baremetal/7', $this->path());
        $fip->assign('baremetal', strtoupper(self::UUID), '1.2.3.4');
        $this->assertSame('/floating_ips/assign/baremetal/' . self::UUID, $this->path());
        $this->assertSame('address=1.2.3.4', end($this->history)['request']->getUri()->getQuery());
    }

    public function testFloatingIPAssignBaremetalRejectsInvalidReference(): void
    {
        $fip = $this->client($this->ok(1))->floatingIPs();
        $this->expectException(\InvalidArgumentException::class);
        try {
            $fip->assign('baremetal', 'abc', '1.2.3.4');
        } finally {
            $this->assertCount(0, $this->history);
        }
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

    public function testNormalizeAndMatchesHelpers(): void
    {
        $this->assertSame('12', BaremetalRef::normalize(12));
        $this->assertSame(self::UUID, BaremetalRef::normalize(strtoupper(self::UUID)));
        $this->assertTrue(BaremetalRef::matches(['id' => 12], '12'));
        $this->assertTrue(BaremetalRef::matches(['id' => '12'], '012'));
        $this->assertFalse(BaremetalRef::matches(['id' => 12], '120'));
        $this->assertFalse(BaremetalRef::matches(['id' => 0], '1'));
        $this->assertFalse(BaremetalRef::matches(['id' => 12], self::UUID));
        $this->assertTrue(BaremetalRef::matches(['id' => 12, 'uuid' => strtoupper(self::UUID)], self::UUID));
    }
}
