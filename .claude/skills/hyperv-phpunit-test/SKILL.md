---
name: hyperv-phpunit-test
description: Writes PHPUnit 9.6 test cases in tests/PluginTest.php under namespace Detain\MyAdminHyperv\Tests\. Use when user says 'write a test', 'add test coverage', 'test this method', or modifies src/Plugin.php. Bootstraps via vendor/autoload.php, references phpunit.xml.dist config. Do NOT use for integration tests against live Hyper-V hosts or bin/ script testing.
---
# HyperV PHPUnit Test

## Critical

- **Never** instantiate `SoapClient` or call `get_service_master()` / `get_module_db()` in tests — these require live infrastructure. Test only pure static methods via reflection and direct calls.
- All tests live in **one file**: `tests/PluginTest.php`. Do not create additional test files.
- Use `@test` docblock annotation — **not** the `testMethodName` prefix. Methods are camelCase without a `test` prefix.
- Guard every `define()` with `if (!defined('CONST_NAME'))` and every stub function with `if (!function_exists('fn_name'))` to prevent redefinition errors across test runs.
- Run tests with `phpunit` (config: `phpunit.xml.dist`).

## Instructions

### 1. Verify file structure

Confirm `tests/PluginTest.php` exists and `src/Plugin.php` has been read before writing any test. Understand which methods are static vs instance, their parameter types, and return shapes.

### 2. Add namespace and imports (top of file, once)

```php
<?php

namespace Detain\MyAdminHyperv\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
```

Verify these imports are present before adding test methods.

### 3. Declare the test class (once)

```php
class PluginTest extends TestCase
{
    /**
     * @var ReflectionClass<\Detain\MyAdminHyperv\Plugin>
     */
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        $this->reflection = new ReflectionClass(\Detain\MyAdminHyperv\Plugin::class);
    }
```

### 4. Organize tests into named groups with separator comments

Group related tests with a `// ---` comment block:

```php
    // ---------------------------------------------------------------
    // Group name tests
    // ---------------------------------------------------------------
```

Standard groups (in order): Class structure → Static properties → Constructor → Method signatures → Pure method return values → Edge cases.

### 5. Write each test method

Exact format — `@test` annotation, camelCase name, `void` return type, no `test` prefix:

```php
    /**
     * @test
     * One-line description of what is verified.
     */
    public function methodNameDescribesBehavior(): void
    {
        // Arrange: use $this->createMinimalServiceInfo() for serviceInfo fixtures
        // Act: call Plugin static methods with full FQCN
        $result = \Detain\MyAdminHyperv\Plugin::getSoapClientParams();
        // Assert
        $this->assertIsArray($result);
    }
```

### 6. Use `createMinimalServiceInfo()` for serviceInfo fixtures

This step uses output from Step 3. Add this private helper once at the bottom of the class:

```php
    private function createMinimalServiceInfo(): array
    {
        return [
            'vps_vzid'     => 'default-uuid',
            'vps_hostname' => 'default.example.com',
            'vps_slices'   => 1,
            'vps_os'       => 'windows2019',
            'vps_id'       => 1,
            'vps_custid'   => 1,
            'vps_ip'       => '10.0.0.100',
            'server_info'  => [
                'vps_ip'   => '192.168.1.1',
                'vps_root' => 'defaultpass',
                'vps_name' => 'hyperv-server-1',
                'vps_id'   => 1,
            ],
            'settings' => [
                'additional_hd' => 0,
                'slice_ram'     => 512,
                'slice_hd'      => 40,
                'PREFIX'        => 'vps',
            ],
        ];
    }
```

Then override only the keys under test:

```php
$serviceInfo = $this->createMinimalServiceInfo();
$serviceInfo['vps_vzid'] = 'test-uuid-1234';
$serviceInfo['server_info']['vps_root'] = 'testpass';
```

### 7. Guard constants and stub functions before each test that needs them

```php
if (!defined('VPS_SLICE_HD')) {
    define('VPS_SLICE_HD', 40);
}
if (!defined('VPS_SLICE_RAM')) {
    define('VPS_SLICE_RAM', 512);
}
if (!function_exists('vps_get_password')) {
    function vps_get_password($id, $custid) {
        return 'generated-pass-' . $id;
    }
}
```

Other constants used in `src/Plugin.php`: `VPS_SLICE_HYPERV_IO_MIN_BASE`, `VPS_SLICE_HYPERV_IO_MIN_MULT`, `VPS_SLICE_HYPERV_IO_MAX_BASE`, `VPS_SLICE_HYPERV_IO_MAX_MULT`, `VPS_HYPERV_PASSWORD`.

### 8. Use ReflectionClass for signature/structural assertions

```php
$method = $this->reflection->getMethod('getActivate');
$this->assertTrue($method->isPublic());
$this->assertTrue($method->isStatic());
$this->assertSame(1, $method->getNumberOfParameters());
$param = $method->getParameters()[0];
$this->assertSame(
    'Symfony\\Component\\EventDispatcher\\GenericEvent',
    $param->getType()->getName()
);
```

### 9. Run and verify

```bash
phpunit
```

Expect: green, zero failures, zero risky (config `phpunit.xml.dist` has `failOnRisky="true"`).

## Examples

**User says:** "Add a test that verifies `getSoapCallParams` for `TurnOff` always uses `Administrator` as `hyperVAdmin`."

**Actions taken:**
1. Read `src/Plugin.php` to confirm `getSoapCallParams('TurnOff', ...)` returns `['hyperVAdmin' => 'Administrator', ...]`.
2. Add to `tests/PluginTest.php` inside the `getSoapCallParams()` group:

```php
    /**
     * @test
     * Verify getSoapCallParams for TurnOff uses Administrator as hyperVAdmin.
     */
    public function getSoapCallParamsTurnOffUsesAdministratorAdmin(): void
    {
        $serviceInfo = $this->createMinimalServiceInfo();
        $serviceInfo['vps_vzid'] = 'vm-uuid-off';
        $serviceInfo['server_info']['vps_root'] = 'rootpass';

        $result = \Detain\MyAdminHyperv\Plugin::getSoapCallParams('TurnOff', $serviceInfo);

        $this->assertArrayHasKey('hyperVAdmin', $result);
        $this->assertSame('Administrator', $result['hyperVAdmin']);
        $this->assertSame('vm-uuid-off', $result['vmId']);
    }
```

3. Run `phpunit` — confirm 1 new passing test.

**Result:** Test added inside existing group, no new files, `phpunit` is green.

## Common Issues

**"Cannot redeclare constant VPS_SLICE_HD"**
Wrap every `define()` with `if (!defined('VPS_SLICE_HD')) { define('VPS_SLICE_HD', 40); }`. PHPUnit runs all test methods in the same process.

**"Cannot redeclare function vps_get_password"**
Wrap stub functions: `if (!function_exists('vps_get_password')) { function vps_get_password(...) { ... } }`.

**"Test method ... is not in camelCase" (risky)**
Config has `failOnRisky="true"`. Method names must be camelCase and use `@test` annotation — not the `testSomething` prefix pattern.

**"Output during test execution" (risky)**
Config has `beStrictAboutOutputDuringTests="true"`. Remove all `var_dump`, `print_r`, and `echo` from test methods.

**"Class 'Detain\\MyAdminHyperv\\Plugin' not found"**
Run `composer install` first. The bootstrap is configured in `phpunit.xml.dist`.

**`$param->getType()` returns null on PHP 7.4**
Call `$this->assertNotNull($param->getType())` before calling `->getName()` — avoids fatal error on untyped parameters.

**`getSoapClientParams()` stream_context comparison fails**
`stream_context_create()` returns a unique resource each call. Unset before comparing:
```php
unset($first['stream_context'], $second['stream_context']);
$this->assertSame($first, $second);
```
