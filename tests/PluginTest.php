<?php

namespace Detain\MyAdminHyperv\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Tests for the Detain\MyAdminHyperv\Plugin class.
 *
 * These tests verify class structure, static properties, pure methods,
 * event handler signatures, and SOAP parameter construction without
 * requiring external dependencies or database connections.
 */
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

    // ---------------------------------------------------------------
    // Class structure tests
    // ---------------------------------------------------------------

    /**
     * @test
     * Verify that the Plugin class exists and is instantiable.
     */
    public function classExists(): void
    {
        $this->assertTrue(class_exists(\Detain\MyAdminHyperv\Plugin::class));
    }

    /**
     * @test
     * Verify the class resides in the expected namespace.
     */
    public function classNamespace(): void
    {
        $this->assertSame('Detain\MyAdminHyperv', $this->reflection->getNamespaceName());
    }

    /**
     * @test
     * Verify the class is not abstract and not an interface.
     */
    public function classIsConcreteAndNotAbstract(): void
    {
        $this->assertFalse($this->reflection->isAbstract());
        $this->assertFalse($this->reflection->isInterface());
    }

    // ---------------------------------------------------------------
    // Static property tests
    // ---------------------------------------------------------------

    /**
     * @test
     * Verify the $name static property equals 'HyperV VPS'.
     */
    public function staticPropertyName(): void
    {
        $this->assertSame('HyperV VPS', \Detain\MyAdminHyperv\Plugin::$name);
    }

    /**
     * @test
     * Verify the $description static property is a non-empty string.
     */
    public function staticPropertyDescription(): void
    {
        $this->assertIsString(\Detain\MyAdminHyperv\Plugin::$description);
        $this->assertNotEmpty(\Detain\MyAdminHyperv\Plugin::$description);
    }

    /**
     * @test
     * Verify the $help static property is an empty string.
     */
    public function staticPropertyHelp(): void
    {
        $this->assertSame('', \Detain\MyAdminHyperv\Plugin::$help);
    }

    /**
     * @test
     * Verify the $module static property equals 'vps'.
     */
    public function staticPropertyModule(): void
    {
        $this->assertSame('vps', \Detain\MyAdminHyperv\Plugin::$module);
    }

    /**
     * @test
     * Verify the $type static property equals 'service'.
     */
    public function staticPropertyType(): void
    {
        $this->assertSame('service', \Detain\MyAdminHyperv\Plugin::$type);
    }

    // ---------------------------------------------------------------
    // Constructor test
    // ---------------------------------------------------------------

    /**
     * @test
     * Verify the constructor is public and takes no required parameters.
     */
    public function constructorIsPublicWithNoRequiredParams(): void
    {
        $constructor = $this->reflection->getConstructor();
        $this->assertNotNull($constructor);
        $this->assertTrue($constructor->isPublic());
        $this->assertSame(0, $constructor->getNumberOfRequiredParameters());
    }

    // ---------------------------------------------------------------
    // getHooks() tests
    // ---------------------------------------------------------------

    /**
     * @test
     * Verify getHooks returns an array.
     */
    public function getHooksReturnsArray(): void
    {
        $hooks = \Detain\MyAdminHyperv\Plugin::getHooks();
        $this->assertIsArray($hooks);
    }

    /**
     * @test
     * Verify getHooks contains the expected event keys.
     */
    public function getHooksContainsExpectedKeys(): void
    {
        $hooks = \Detain\MyAdminHyperv\Plugin::getHooks();
        $this->assertArrayHasKey('vps.settings', $hooks);
        $this->assertArrayHasKey('vps.deactivate', $hooks);
        $this->assertArrayHasKey('vps.queue', $hooks);
    }

    /**
     * @test
     * Verify getHooks does not include the commented-out activate hook.
     */
    public function getHooksDoesNotIncludeActivateHook(): void
    {
        $hooks = \Detain\MyAdminHyperv\Plugin::getHooks();
        $this->assertArrayNotHasKey('vps.activate', $hooks);
    }

    /**
     * @test
     * Verify each hook value is a callable-style array with class and method.
     */
    public function getHooksValuesAreCallableArrays(): void
    {
        $hooks = \Detain\MyAdminHyperv\Plugin::getHooks();
        foreach ($hooks as $key => $value) {
            $this->assertIsArray($value, "Hook '{$key}' value should be an array");
            $this->assertCount(2, $value, "Hook '{$key}' should have exactly 2 elements");
            $this->assertSame(\Detain\MyAdminHyperv\Plugin::class, $value[0], "Hook '{$key}' first element should be the class name");
            $this->assertIsString($value[1], "Hook '{$key}' second element should be a string method name");
        }
    }

    /**
     * @test
     * Verify each method referenced in getHooks actually exists on the Plugin class.
     */
    public function getHooksMethodsExistOnClass(): void
    {
        $hooks = \Detain\MyAdminHyperv\Plugin::getHooks();
        foreach ($hooks as $key => $value) {
            $this->assertTrue(
                $this->reflection->hasMethod($value[1]),
                "Method '{$value[1]}' referenced in hook '{$key}' should exist on Plugin class"
            );
        }
    }

    // ---------------------------------------------------------------
    // getQueueCalls() tests
    // ---------------------------------------------------------------

    /**
     * @test
     * Verify getQueueCalls returns an array.
     */
    public function getQueueCallsReturnsArray(): void
    {
        $result = \Detain\MyAdminHyperv\Plugin::getQueueCalls();
        $this->assertIsArray($result);
    }

    /**
     * @test
     * Verify getQueueCalls contains the expected action keys.
     */
    public function getQueueCallsContainsExpectedActions(): void
    {
        $calls = \Detain\MyAdminHyperv\Plugin::getQueueCalls();
        $expectedActions = ['restart', 'enable', 'start', 'delete', 'stop', 'destroy', 'reinstall_os', 'set_slices'];
        foreach ($expectedActions as $action) {
            $this->assertArrayHasKey($action, $calls, "Action '{$action}' should exist in queue calls");
        }
    }

    /**
     * @test
     * Verify each queue call action maps to an array of string SOAP method names.
     */
    public function getQueueCallsValuesAreStringArrays(): void
    {
        $calls = \Detain\MyAdminHyperv\Plugin::getQueueCalls();
        foreach ($calls as $action => $methods) {
            $this->assertIsArray($methods, "Action '{$action}' should map to an array");
            foreach ($methods as $method) {
                $this->assertIsString($method, "Each method for action '{$action}' should be a string");
            }
        }
    }

    /**
     * @test
     * Verify the restart action maps to the Reboot SOAP call.
     */
    public function getQueueCallsRestartMapsToReboot(): void
    {
        $calls = \Detain\MyAdminHyperv\Plugin::getQueueCalls();
        $this->assertSame(['Reboot'], $calls['restart']);
    }

    /**
     * @test
     * Verify the destroy action maps to TurnOff followed by DeleteVM.
     */
    public function getQueueCallsDestroySequence(): void
    {
        $calls = \Detain\MyAdminHyperv\Plugin::getQueueCalls();
        $this->assertSame(['TurnOff', 'DeleteVM'], $calls['destroy']);
    }

    /**
     * @test
     * Verify the set_slices action maps to the full resize sequence.
     */
    public function getQueueCallsSetSlicesSequence(): void
    {
        $calls = \Detain\MyAdminHyperv\Plugin::getQueueCalls();
        $this->assertSame(
            ['TurnOff', 'ResizeVMHardDrive', 'UpdateVM', 'SetVMIOPS', 'TurnON'],
            $calls['set_slices']
        );
    }

    /**
     * @test
     * Verify that enable and start both map to TurnON.
     */
    public function getQueueCallsEnableAndStartBothMapToTurnOn(): void
    {
        $calls = \Detain\MyAdminHyperv\Plugin::getQueueCalls();
        $this->assertSame(['TurnON'], $calls['enable']);
        $this->assertSame(['TurnON'], $calls['start']);
    }

    /**
     * @test
     * Verify that delete and stop both map to TurnOff.
     */
    public function getQueueCallsDeleteAndStopBothMapToTurnOff(): void
    {
        $calls = \Detain\MyAdminHyperv\Plugin::getQueueCalls();
        $this->assertSame(['TurnOff'], $calls['delete']);
        $this->assertSame(['TurnOff'], $calls['stop']);
    }

    // ---------------------------------------------------------------
    // getSoapClientUrl() tests
    // ---------------------------------------------------------------

    /**
     * @test
     * Verify getSoapClientUrl returns the expected WSDL URL format.
     */
    public function getSoapClientUrlReturnsCorrectFormat(): void
    {
        $url = \Detain\MyAdminHyperv\Plugin::getSoapClientUrl('192.168.1.100');
        $this->assertSame('https://192.168.1.100/HyperVService/HyperVService.asmx?WSDL', $url);
    }

    /**
     * @test
     * Verify getSoapClientUrl works with a hostname.
     */
    public function getSoapClientUrlWorksWithHostname(): void
    {
        $url = \Detain\MyAdminHyperv\Plugin::getSoapClientUrl('hyperv.example.com');
        $this->assertSame('https://hyperv.example.com/HyperVService/HyperVService.asmx?WSDL', $url);
    }

    /**
     * @test
     * Verify getSoapClientUrl always uses HTTPS.
     */
    public function getSoapClientUrlUsesHttps(): void
    {
        $url = \Detain\MyAdminHyperv\Plugin::getSoapClientUrl('10.0.0.1');
        $this->assertStringStartsWith('https://', $url);
    }

    /**
     * @test
     * Verify getSoapClientUrl ends with the WSDL query string.
     */
    public function getSoapClientUrlEndsWithWsdl(): void
    {
        $url = \Detain\MyAdminHyperv\Plugin::getSoapClientUrl('10.0.0.1');
        $this->assertStringEndsWith('?WSDL', $url);
    }

    // ---------------------------------------------------------------
    // getSoapClientParams() tests
    // ---------------------------------------------------------------

    /**
     * @test
     * Verify getSoapClientParams returns an array.
     */
    public function getSoapClientParamsReturnsArray(): void
    {
        $params = \Detain\MyAdminHyperv\Plugin::getSoapClientParams();
        $this->assertIsArray($params);
    }

    /**
     * @test
     * Verify getSoapClientParams includes UTF-8 encoding.
     */
    public function getSoapClientParamsHasUtf8Encoding(): void
    {
        $params = \Detain\MyAdminHyperv\Plugin::getSoapClientParams();
        $this->assertArrayHasKey('encoding', $params);
        $this->assertSame('UTF-8', $params['encoding']);
    }

    /**
     * @test
     * Verify getSoapClientParams uses SOAP 1.2.
     */
    public function getSoapClientParamsUsesSoap12(): void
    {
        $params = \Detain\MyAdminHyperv\Plugin::getSoapClientParams();
        $this->assertArrayHasKey('soap_version', $params);
        $this->assertSame(SOAP_1_2, $params['soap_version']);
    }

    /**
     * @test
     * Verify getSoapClientParams enables trace and exceptions.
     */
    public function getSoapClientParamsEnablesTraceAndExceptions(): void
    {
        $params = \Detain\MyAdminHyperv\Plugin::getSoapClientParams();
        $this->assertSame(1, $params['trace']);
        $this->assertSame(1, $params['exceptions']);
    }

    /**
     * @test
     * Verify getSoapClientParams has a 600-second connection timeout.
     */
    public function getSoapClientParamsHas600SecondTimeout(): void
    {
        $params = \Detain\MyAdminHyperv\Plugin::getSoapClientParams();
        $this->assertArrayHasKey('connection_timeout', $params);
        $this->assertSame(600, $params['connection_timeout']);
    }

    /**
     * @test
     * Verify getSoapClientParams disables peer verification.
     */
    public function getSoapClientParamsDisablesPeerVerification(): void
    {
        $params = \Detain\MyAdminHyperv\Plugin::getSoapClientParams();
        $this->assertFalse($params['verifypeer']);
        $this->assertFalse($params['verifyhost']);
    }

    /**
     * @test
     * Verify getSoapClientParams includes a stream_context resource.
     */
    public function getSoapClientParamsIncludesStreamContext(): void
    {
        $params = \Detain\MyAdminHyperv\Plugin::getSoapClientParams();
        $this->assertArrayHasKey('stream_context', $params);
        $this->assertIsResource($params['stream_context']);
    }

    // ---------------------------------------------------------------
    // getSoapCallParams() tests
    // ---------------------------------------------------------------

    /**
     * @test
     * Verify getSoapCallParams returns empty array for CleanUpResources.
     */
    public function getSoapCallParamsCleanUpResourcesReturnsEmptyArray(): void
    {
        $serviceInfo = $this->createMinimalServiceInfo();
        $result = \Detain\MyAdminHyperv\Plugin::getSoapCallParams('CleanUpResources', $serviceInfo);
        $this->assertSame([], $result);
    }

    /**
     * @test
     * Verify getSoapCallParams for default calls includes vmId and admin credentials.
     */
    public function getSoapCallParamsDefaultCallIncludesVmIdAndCredentials(): void
    {
        $serviceInfo = $this->createMinimalServiceInfo();
        $serviceInfo['vps_vzid'] = 'test-uuid-1234';
        $serviceInfo['server_info']['vps_root'] = 'testpass';

        $result = \Detain\MyAdminHyperv\Plugin::getSoapCallParams('TurnON', $serviceInfo);

        $this->assertArrayHasKey('vmId', $result);
        $this->assertSame('test-uuid-1234', $result['vmId']);
        $this->assertArrayHasKey('hyperVAdmin', $result);
        $this->assertSame('Administrator', $result['hyperVAdmin']);
        $this->assertArrayHasKey('adminPassword', $result);
        $this->assertSame('testpass', $result['adminPassword']);
    }

    /**
     * @test
     * Verify getSoapCallParams for GetVMList includes admin credentials but no vmId.
     */
    public function getSoapCallParamsGetVMListIncludesCredentialsNoVmId(): void
    {
        $serviceInfo = $this->createMinimalServiceInfo();
        $serviceInfo['server_info']['vps_root'] = 'adminpass';

        $result = \Detain\MyAdminHyperv\Plugin::getSoapCallParams('GetVMList', $serviceInfo);

        $this->assertArrayHasKey('hyperVAdmin', $result);
        $this->assertSame('Administrator', $result['hyperVAdmin']);
        $this->assertArrayHasKey('adminPassword', $result);
        $this->assertSame('adminpass', $result['adminPassword']);
        $this->assertArrayNotHasKey('vmId', $result);
    }

    /**
     * @test
     * Verify getSoapCallParams for CreateVM includes all required creation parameters.
     */
    public function getSoapCallParamsCreateVMIncludesAllCreationParams(): void
    {
        $serviceInfo = $this->createMinimalServiceInfo();
        $serviceInfo['vps_hostname'] = 'test-vm.example.com';
        $serviceInfo['vps_slices'] = 2;
        $serviceInfo['vps_os'] = 'windows2019';
        $serviceInfo['server_info']['vps_root'] = 'rootpass';
        $serviceInfo['settings']['additional_hd'] = 0;

        if (!defined('VPS_SLICE_HD')) {
            define('VPS_SLICE_HD', 40);
        }
        if (!defined('VPS_SLICE_RAM')) {
            define('VPS_SLICE_RAM', 512);
        }

        $result = \Detain\MyAdminHyperv\Plugin::getSoapCallParams('CreateVM', $serviceInfo);

        $this->assertArrayHasKey('vmName', $result);
        $this->assertSame('test-vm.example.com', $result['vmName']);
        $this->assertArrayHasKey('vhdSize', $result);
        $this->assertArrayHasKey('ramSize', $result);
        $this->assertArrayHasKey('dynamicMemorySliceValue', $result);
        $this->assertSame(0, $result['dynamicMemorySliceValue']);
        $this->assertArrayHasKey('osToInstall', $result);
        $this->assertSame('windows2019', $result['osToInstall']);
        $this->assertArrayHasKey('hyperVAdmin', $result);
        $this->assertSame('Administrator', $result['hyperVAdmin']);
    }

    /**
     * @test
     * Verify getSoapCallParams for UpdateVM includes cpuCores and ramMB.
     */
    public function getSoapCallParamsUpdateVMIncludesCpuAndRam(): void
    {
        $serviceInfo = $this->createMinimalServiceInfo();
        $serviceInfo['vps_vzid'] = 'vm-uuid-5678';
        $serviceInfo['vps_slices'] = 4;
        $serviceInfo['server_info']['vps_root'] = 'rootpass';

        if (!defined('VPS_SLICE_RAM')) {
            define('VPS_SLICE_RAM', 512);
        }

        $result = \Detain\MyAdminHyperv\Plugin::getSoapCallParams('UpdateVM', $serviceInfo);

        $this->assertArrayHasKey('vmId', $result);
        $this->assertSame('vm-uuid-5678', $result['vmId']);
        $this->assertArrayHasKey('cpuCores', $result);
        $this->assertArrayHasKey('ramMB', $result);
        $this->assertArrayHasKey('bootFromCD', $result);
        $this->assertFalse($result['bootFromCD']);
        $this->assertArrayHasKey('numLockEnabled', $result);
        $this->assertTrue($result['numLockEnabled']);
    }

    /**
     * @test
     * Verify getSoapCallParams UpdateVM cpuCores formula: ceil(((slices - 2) / 2) + 1).
     */
    public function getSoapCallParamsUpdateVMCpuCoresFormula(): void
    {
        $serviceInfo = $this->createMinimalServiceInfo();
        $serviceInfo['vps_vzid'] = 'vm-uuid';
        $serviceInfo['server_info']['vps_root'] = 'rootpass';

        // slices=2 => ceil(((2-2)/2)+1) = ceil(0+1) = 1
        $serviceInfo['vps_slices'] = 2;
        $result = \Detain\MyAdminHyperv\Plugin::getSoapCallParams('UpdateVM', $serviceInfo);
        $this->assertSame(1, $result['cpuCores']);

        // slices=4 => ceil(((4-2)/2)+1) = ceil(1+1) = 2
        $serviceInfo['vps_slices'] = 4;
        $result = \Detain\MyAdminHyperv\Plugin::getSoapCallParams('UpdateVM', $serviceInfo);
        $this->assertSame(2, $result['cpuCores']);

        // slices=5 => ceil(((5-2)/2)+1) = ceil(1.5+1) = ceil(2.5) = 3
        $serviceInfo['vps_slices'] = 5;
        $result = \Detain\MyAdminHyperv\Plugin::getSoapCallParams('UpdateVM', $serviceInfo);
        $this->assertSame(3, $result['cpuCores']);
    }

    /**
     * @test
     * Verify getSoapCallParams for ResizeVMHardDrive includes drive size and credentials.
     */
    public function getSoapCallParamsResizeVMHardDriveIncludesDriveSize(): void
    {
        $serviceInfo = $this->createMinimalServiceInfo();
        $serviceInfo['vps_vzid'] = 'vm-uuid-resize';
        $serviceInfo['vps_slices'] = 3;
        $serviceInfo['server_info']['vps_root'] = 'rootpass';
        $serviceInfo['settings']['additional_hd'] = 10;

        $result = \Detain\MyAdminHyperv\Plugin::getSoapCallParams('ResizeVMHardDrive', $serviceInfo);

        $this->assertArrayHasKey('vmId', $result);
        $this->assertSame('vm-uuid-resize', $result['vmId']);
        $this->assertArrayHasKey('updatedDriveSizeInGigabytes', $result);
        $this->assertArrayHasKey('hyperVAdminUsername', $result);
        $this->assertSame('Administrator', $result['hyperVAdminUsername']);
    }

    /**
     * @test
     * Verify getSoapCallParams for SetVMIOPS includes IOPS parameters.
     */
    public function getSoapCallParamsSetVMIOPSIncludesIopsParams(): void
    {
        $serviceInfo = $this->createMinimalServiceInfo();
        $serviceInfo['vps_vzid'] = 'vm-uuid-iops';
        $serviceInfo['vps_slices'] = 2;
        $serviceInfo['server_info']['vps_root'] = 'rootpass';

        if (!defined('VPS_SLICE_HYPERV_IO_MIN_BASE')) {
            define('VPS_SLICE_HYPERV_IO_MIN_BASE', 100);
        }
        if (!defined('VPS_SLICE_HYPERV_IO_MIN_MULT')) {
            define('VPS_SLICE_HYPERV_IO_MIN_MULT', 50);
        }
        if (!defined('VPS_SLICE_HYPERV_IO_MAX_BASE')) {
            define('VPS_SLICE_HYPERV_IO_MAX_BASE', 500);
        }
        if (!defined('VPS_SLICE_HYPERV_IO_MAX_MULT')) {
            define('VPS_SLICE_HYPERV_IO_MAX_MULT', 100);
        }

        $result = \Detain\MyAdminHyperv\Plugin::getSoapCallParams('SetVMIOPS', $serviceInfo);

        $this->assertArrayHasKey('vmId', $result);
        $this->assertSame('vm-uuid-iops', $result['vmId']);
        $this->assertArrayHasKey('minimumOps', $result);
        $this->assertArrayHasKey('maximumOps', $result);
        $this->assertSame(200, $result['minimumOps']); // 100 + (50 * 2)
        $this->assertSame(700, $result['maximumOps']); // 500 + (100 * 2)
        $this->assertArrayHasKey('adminUsername', $result);
        $this->assertSame('Administrator', $result['adminUsername']);
    }

    /**
     * @test
     * Verify getSoapCallParams for SetVMAdminPassword includes password change params.
     */
    public function getSoapCallParamsSetVMAdminPasswordIncludesPasswordParams(): void
    {
        $serviceInfo = $this->createMinimalServiceInfo();
        $serviceInfo['vps_vzid'] = 'vm-uuid-pass';
        $serviceInfo['vps_id'] = 42;
        $serviceInfo['vps_custid'] = 100;
        $serviceInfo['server_info']['vps_root'] = 'rootpass';

        if (!defined('VPS_HYPERV_PASSWORD')) {
            define('VPS_HYPERV_PASSWORD', 'OldPassword123');
        }
        if (!function_exists('vps_get_password')) {
            function vps_get_password($id, $custid)
            {
                return 'generated-pass-' . $id;
            }
        }

        $result = \Detain\MyAdminHyperv\Plugin::getSoapCallParams('SetVMAdminPassword', $serviceInfo);

        $this->assertArrayHasKey('adminUser', $result);
        $this->assertSame('Administrator', $result['adminUser']);
        $this->assertArrayHasKey('adminPassword', $result);
        $this->assertSame('rootpass', $result['adminPassword']);
        $this->assertArrayHasKey('vmId', $result);
        $this->assertSame('vm-uuid-pass', $result['vmId']);
        $this->assertArrayHasKey('username', $result);
        $this->assertSame('Administrator', $result['username']);
        $this->assertArrayHasKey('existingPassword', $result);
        $this->assertArrayHasKey('newPassword', $result);
    }

    // ---------------------------------------------------------------
    // Event handler signature tests
    // ---------------------------------------------------------------

    /**
     * @test
     * Verify getActivate is a public static method accepting a GenericEvent parameter.
     */
    public function getActivateMethodSignature(): void
    {
        $method = $this->reflection->getMethod('getActivate');
        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isStatic());
        $this->assertSame(1, $method->getNumberOfParameters());

        $param = $method->getParameters()[0];
        $this->assertNotNull($param->getType());
        $this->assertSame(
            'Symfony\Component\EventDispatcher\GenericEvent',
            $param->getType()->getName()
        );
    }

    /**
     * @test
     * Verify getDeactivate is a public static method accepting a GenericEvent parameter.
     */
    public function getDeactivateMethodSignature(): void
    {
        $method = $this->reflection->getMethod('getDeactivate');
        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isStatic());
        $this->assertSame(1, $method->getNumberOfParameters());

        $param = $method->getParameters()[0];
        $this->assertNotNull($param->getType());
        $this->assertSame(
            'Symfony\Component\EventDispatcher\GenericEvent',
            $param->getType()->getName()
        );
    }

    /**
     * @test
     * Verify getSettings is a public static method accepting a GenericEvent parameter.
     */
    public function getSettingsMethodSignature(): void
    {
        $method = $this->reflection->getMethod('getSettings');
        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isStatic());
        $this->assertSame(1, $method->getNumberOfParameters());

        $param = $method->getParameters()[0];
        $this->assertNotNull($param->getType());
        $this->assertSame(
            'Symfony\Component\EventDispatcher\GenericEvent',
            $param->getType()->getName()
        );
    }

    /**
     * @test
     * Verify getQueue is a public static method accepting a GenericEvent parameter.
     */
    public function getQueueMethodSignature(): void
    {
        $method = $this->reflection->getMethod('getQueue');
        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isStatic());
        $this->assertSame(1, $method->getNumberOfParameters());

        $param = $method->getParameters()[0];
        $this->assertNotNull($param->getType());
        $this->assertSame(
            'Symfony\Component\EventDispatcher\GenericEvent',
            $param->getType()->getName()
        );
    }

    // ---------------------------------------------------------------
    // Method existence tests
    // ---------------------------------------------------------------

    /**
     * @test
     * Verify all expected public static methods exist on the Plugin class.
     */
    public function allExpectedMethodsExist(): void
    {
        $expectedMethods = [
            'getHooks',
            'getActivate',
            'getDeactivate',
            'getSettings',
            'getQueue',
            'getQueueCalls',
            'getSoapCallParams',
            'getSoapClientUrl',
            'getSoapClientParams',
            'queueCreate',
        ];

        foreach ($expectedMethods as $methodName) {
            $this->assertTrue(
                $this->reflection->hasMethod($methodName),
                "Method '{$methodName}' should exist on Plugin class"
            );
        }
    }

    /**
     * @test
     * Verify that getSoapClientUrl, getSoapClientParams, getHooks, and getQueueCalls are all static.
     */
    public function pureHelperMethodsAreStatic(): void
    {
        $staticMethods = [
            'getSoapClientUrl',
            'getSoapClientParams',
            'getHooks',
            'getQueueCalls',
            'getSoapCallParams',
            'queueCreate',
        ];

        foreach ($staticMethods as $methodName) {
            $method = $this->reflection->getMethod($methodName);
            $this->assertTrue(
                $method->isStatic(),
                "Method '{$methodName}' should be static"
            );
        }
    }

    /**
     * @test
     * Verify queueCreate is a public static method.
     */
    public function queueCreateMethodSignature(): void
    {
        $method = $this->reflection->getMethod('queueCreate');
        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isStatic());
        $this->assertSame(1, $method->getNumberOfRequiredParameters());
    }

    // ---------------------------------------------------------------
    // Static analysis / integration-safe tests
    // ---------------------------------------------------------------

    /**
     * @test
     * Verify that getSoapCallParams always uses 'Administrator' as the admin username
     * across all call types that include admin credentials.
     */
    public function getSoapCallParamsAlwaysUsesAdministratorUsername(): void
    {
        $serviceInfo = $this->createMinimalServiceInfo();
        $serviceInfo['vps_vzid'] = 'test-uuid';
        $serviceInfo['server_info']['vps_root'] = 'pass';

        $callsWithAdmin = ['TurnON', 'TurnOff', 'Reboot', 'DeleteVM', 'ShutDown', 'GetVM'];

        foreach ($callsWithAdmin as $call) {
            $result = \Detain\MyAdminHyperv\Plugin::getSoapCallParams($call, $serviceInfo);
            $this->assertSame(
                'Administrator',
                $result['hyperVAdmin'],
                "Call '{$call}' should use 'Administrator' as hyperVAdmin"
            );
        }
    }

    /**
     * @test
     * Verify the hooks use the module prefix for event names.
     */
    public function hooksUseModulePrefixForEventNames(): void
    {
        $hooks = \Detain\MyAdminHyperv\Plugin::getHooks();
        $module = \Detain\MyAdminHyperv\Plugin::$module;

        foreach (array_keys($hooks) as $eventName) {
            $this->assertStringStartsWith(
                $module . '.',
                $eventName,
                "Event name '{$eventName}' should start with module prefix '{$module}.'"
            );
        }
    }

    /**
     * @test
     * Verify getSoapClientParams returns consistent results across multiple calls.
     */
    public function getSoapClientParamsIsIdempotent(): void
    {
        $first = \Detain\MyAdminHyperv\Plugin::getSoapClientParams();
        $second = \Detain\MyAdminHyperv\Plugin::getSoapClientParams();

        // Compare all keys except stream_context (resources are unique)
        unset($first['stream_context'], $first['context']);
        unset($second['stream_context'], $second['context']);

        $this->assertSame($first, $second);
    }

    // ---------------------------------------------------------------
    // Helper methods
    // ---------------------------------------------------------------

    /**
     * Creates a minimal serviceInfo array for testing getSoapCallParams.
     */
    private function createMinimalServiceInfo(): array
    {
        return [
            'vps_vzid' => 'default-uuid',
            'vps_hostname' => 'default.example.com',
            'vps_slices' => 1,
            'vps_os' => 'windows2019',
            'vps_id' => 1,
            'vps_custid' => 1,
            'vps_ip' => '10.0.0.100',
            'server_info' => [
                'vps_ip' => '192.168.1.1',
                'vps_root' => 'defaultpass',
                'vps_name' => 'hyperv-server-1',
                'vps_id' => 1,
            ],
            'settings' => [
                'additional_hd' => 0,
                'slice_ram' => 512,
                'slice_hd' => 40,
                'PREFIX' => 'vps',
            ],
        ];
    }
}
