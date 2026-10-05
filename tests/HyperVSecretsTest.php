<?php

declare(strict_types=1);

namespace Detain\MyAdminHyperv\Tests;

use Detain\MyAdminHyperv\Plugin;
use MyAdmin\Plugins\Testing\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * MyAdmin plan_2way §5.1 H1, §5.11 (Q40): the Hyper-V host Administrator
 * password is opened right before each SOAP call, and the raw `password`
 * history row plus the vps_rootpass UPDATE are sealed only once core's write
 * flags are on. With the flags off (plugin-installer's stand-ins, production
 * today) every value is exactly what it was.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class HyperVSecretsTest extends TestCase
{
    private static function call(string $method, array $args)
    {
        $m = new \ReflectionMethod(Plugin::class, $method);
        $m->setAccessible(true);
        return $m->invokeArgs(null, $args);
    }

    public function testHostPasswordIsTheStoredPlaintextUnderTodaysFlags(): void
    {
        Bootstrap::init(['module' => 'vps']);
        $this->assertSame("Host'Pw\\1", Plugin::hostPassword(['vps_id' => 440, 'vps_root' => "Host'Pw\\1"]));
        $this->assertNull(Plugin::hostPassword(['vps_id' => 440]));
    }

    public function testAnEnvelopeIsNeverSentToAHost(): void
    {
        Bootstrap::init(['module' => 'vps']);
        $this->expectException(\LogicException::class);
        Plugin::hostPassword(['vps_id' => 440, 'vps_root' => 'S1.k1.' . str_repeat('A', 60)]);
    }

    public function testWithoutCoresClassTheStoredValueIsUsedAsBefore(): void
    {
        $this->assertFalse(class_exists('MyAdmin\\Security\\ServiceSecrets'));
        $this->assertSame('Host-Pw', Plugin::hostPassword(['vps_root' => 'Host-Pw']));
        $this->assertSame('Root-Pw', self::call('historyPassword', ['vps', 123, 'Root-Pw', 5]));
        $this->assertSame('Root-Pw', self::call('rootpassValue', ['vps', 'vps_rootpass', 123, 'Root-Pw']));
    }

    public function testWritersKeepTodaysValuesWhileTheFlagsAreOff(): void
    {
        Bootstrap::init(['module' => 'vps']);
        $this->assertSame("Root'Pw", self::call('historyPassword', ['vps', 123, "Root'Pw", 5]));
        $this->assertSame("Root'Pw", self::call('rootpassValue', ['vps', 'vps_rootpass', 123, "Root'Pw"]));
    }

    public function testEveryLiveHostPasswordReadGoesThroughTheHelper(): void
    {
        $root = dirname(__DIR__);
        $src = (string) file_get_contents($root . '/src/Plugin.php');
        $this->assertStringNotContainsString("\$serviceInfo['server_info']['vps_root']", $src);
        $this->assertSame(17, substr_count($src, "self::hostPassword(\$serviceInfo['server_info'])"));
        $this->assertStringContainsString("'history_old_value' => self::historyPassword(\$settings['PREFIX'], \$serviceInfo['vps_id'], (string)\$pass, (int)\$serviceInfo['vps_custid'])", $src);
        $this->assertStringContainsString("real_escape(self::rootpassValue(\$settings['TABLE'], \$settings['PREFIX'].'_rootpass', (int)\$serviceInfo['vps_id'], (string)\$pass))", $src);
        foreach (glob($root . '/bin/*.php') as $file) {
            foreach (file($file) as $line) {
                if (strpos($line, "\$master['vps_root']") !== false) {
                    $this->assertMatchesRegularExpression('~^\s*//~', $line, basename($file) . ' reads vps_root directly');
                }
            }
        }
    }
}
