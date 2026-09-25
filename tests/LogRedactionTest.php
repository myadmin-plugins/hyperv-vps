<?php

namespace Detain\MyAdminHyperv\Tests;

use Detain\MyAdminHyperv\Plugin;
use PHPUnit\Framework\TestCase;

/**
 * The SOAP parameter arrays and the SetVMAdminPassword calls are logged;
 * the logged copies must not carry the hypervisor admin password or the
 * VPS root password.
 */
class LogRedactionTest extends TestCase
{
    public function testRedactParamsMasksEveryPasswordField(): void
    {
        $params = [
            'vmId' => 'abc-123',
            'hyperVAdmin' => 'Administrator',
            'hyperVAdminPassword' => 'HostSecret1',
            'adminPassword' => 'HostSecret1',
            'existingPassword' => 'TemplatePass1',
            'newPassword' => 'CustomerRoot1',
            'ip' => '192.0.2.5',
        ];
        $out = Plugin::redactParams($params);
        $this->assertSame('[redacted]', $out['hyperVAdminPassword']);
        $this->assertSame('[redacted]', $out['adminPassword']);
        $this->assertSame('[redacted]', $out['existingPassword']);
        $this->assertSame('[redacted]', $out['newPassword']);
        $this->assertSame('abc-123', $out['vmId']);
        $this->assertSame('Administrator', $out['hyperVAdmin']);
        $this->assertSame('192.0.2.5', $out['ip']);
        $encoded = json_encode($out);
        foreach (['HostSecret1', 'TemplatePass1', 'CustomerRoot1'] as $secret) {
            $this->assertStringNotContainsString($secret, $encoded);
        }
        // the parameters that are sent are unchanged
        $this->assertSame('HostSecret1', $params['adminPassword']);
    }

    public function testRedactParamsKeepsEmptyValuesAndRecurses(): void
    {
        $out = Plugin::redactParams(['adminPassword' => '', 'inner' => ['newPassword' => 'x']]);
        $this->assertSame('', $out['adminPassword']);
        $this->assertSame('[redacted]', $out['inner']['newPassword']);
    }

    public function testNoLogLineInterpolatesAPassword(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__) . '/src/Plugin.php');
        preg_match_all('/^\s*myadmin_log\(.*$/m', $source, $lines);
        $this->assertNotEmpty($lines[0]);
        foreach ($lines[0] as $line) {
            $this->assertStringNotContainsString("{\$serviceInfo['origrootpass']}", $line);
            $this->assertStringNotContainsString('{$pass}', $line);
            $this->assertDoesNotMatchRegularExpression('/json_encode\(\$(create|ip|update|password)_parameters\)/', $line);
        }
    }
}
