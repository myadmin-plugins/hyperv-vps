<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap file.
 *
 * Loads the namespace-level function stubs first, then the Composer autoloader.
 */

require_once __DIR__ . '/stubs.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';
