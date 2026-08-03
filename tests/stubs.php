<?php

declare(strict_types=1);

/**
 * Namespace-level stubs for the MyAdmin core functions that Plugin.php calls.
 *
 * src/Plugin.php calls these without a leading backslash, so PHP resolves them
 * in the Detain\MyAdminHyperv namespace before falling back to the global scope.
 * Declaring them here, in that namespace, is what makes the plugin unit-testable
 * without a MyAdmin core tree.
 *
 * These must live in their own file: tests/PluginTest.php declares
 * `namespace Detain\MyAdminHyperv\Tests;` in statement form, so any function
 * defined inside it lands in the Tests namespace and is invisible to Plugin.php.
 */

namespace Detain\MyAdminHyperv {
    if (!function_exists('Detain\\MyAdminHyperv\\vps_get_password')) {
        /**
         * Returns the stored root password for a VPS service.
         *
         * The return value encodes both arguments so tests can assert that
         * Plugin::getSoapCallParams() passes the service id and customer id
         * through in the right order.
         *
         * @param int|string $id     vps_id
         * @param int|string $custid vps_custid
         * @return string
         */
        function vps_get_password($id, $custid): string
        {
            return 'generated-pass-' . $id . '-' . $custid;
        }
    }

    if (!function_exists('Detain\\MyAdminHyperv\\get_module_db')) {
        /**
         * Hands back the fake module DB the current test installed.
         *
         * @param string $module module name, recorded so tests can assert it
         * @return \Detain\MyAdminHyperv\Tests\FakeDb
         */
        function get_module_db($module)
        {
            $GLOBALS['hyperv_test_db_modules'][] = $module;
            return $GLOBALS['hyperv_test_db'];
        }
    }

    if (!function_exists('Detain\\MyAdminHyperv\\function_requirements')) {
        /**
         * Records lazy-load requests instead of resolving them against core.
         *
         * @param string $function function name being lazy-loaded
         * @return bool
         */
        function function_requirements($function)
        {
            $GLOBALS['hyperv_test_requirements'][] = $function;
            return true;
        }
    }

    if (!function_exists('Detain\\MyAdminHyperv\\ipcalc')) {
        /**
         * Returns a fixed subnet breakdown, recording the network it was given.
         *
         * @param string $network network in `ip/cidr` form
         * @return array{hostmin:string, netmask:string}
         */
        function ipcalc($network)
        {
            $GLOBALS['hyperv_test_ipcalc_args'][] = $network;
            return ['hostmin' => '203.0.113.1', 'netmask' => '255.255.255.0'];
        }
    }
}

namespace Detain\MyAdminHyperv\Tests {
    /**
     * Minimal stand-in for \MyDb, covering only the read pattern
     * Plugin::getSoapCallParams() uses for the AddPublicIp vlan lookup.
     */
    class FakeDb
    {
        /** @var array<string, mixed> current row, as next_record() would leave it */
        public $Record = [];

        /** @var string[] every query issued, in order */
        public $queries = [];

        /** @var array<string, mixed> row handed back by next_record() */
        private $row;

        /**
         * @param array<string, mixed> $row row to expose after next_record()
         */
        public function __construct(array $row = [])
        {
            $this->row = $row;
        }

        /**
         * @param string $sql
         * @return true
         */
        public function query($sql)
        {
            $this->queries[] = $sql;
            return true;
        }

        /**
         * @param int|null $type unused; mirrors the core signature
         * @return bool
         */
        public function next_record($type = null)
        {
            $this->Record = $this->row;
            return $this->row !== [];
        }
    }
}

namespace {
    // Core defines this; the AddPublicIp branch passes it to next_record().
    if (!defined('MYSQL_ASSOC')) {
        define('MYSQL_ASSOC', 1);
    }
}
