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
}
