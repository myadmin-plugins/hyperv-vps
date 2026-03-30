# MyAdmin Hyper-V VPS Plugin

PHP plugin for MyAdmin providing full Hyper-V VM lifecycle management via SOAP API. Part of the `detain/myadmin-*` plugin family.

## Commands

```bash
composer install              # install deps including phpunit/phpunit ^9.6
vendor/bin/phpunit            # run tests (config: phpunit.xml.dist)
php bin/GetVMList.php <id>   # test against a live host server
php bin/CheckVMExists.php <id> <vmId>
```

## Architecture

- **Plugin class**: `src/Plugin.php` · namespace `Detain\MyAdminHyperv\` · autoloaded via PSR-4
- **Tests**: `tests/PluginTest.php` · autoloaded under `Detain\MyAdminHyperv\Tests\`
- **CLI scripts**: `bin/` — synchronous SOAP ops · `bin/async/` — ReactPHP async variants
- **CI/CD workflows**: `.github/` contains automated test and deployment pipelines (`.github/workflows/tests.yml`)
- **IDE config**: `.idea/` contains PHPStorm project settings including deployment configuration (`deployment.xml`), file encoding settings (`encodings.xml`), and module definitions
- **SOAP endpoint**: `https://{master_ip}/HyperVService/HyperVService.asmx?WSDL`
- **SOAP params**: always via `\Detain\MyAdminHyperv\Plugin::getSoapClientParams()`
- **Host lookup**: `get_service_master($argv[1], 'vps', true)` → `$master['vps_ip']`, `$master['vps_root']`
- **DB access** (when needed): `get_module_db('vps')` + `$db->real_escape()` — never PDO
- **Functions bootstrap**: `include_once __DIR__.'/../../../../include/functions.inc.php'`

## bin/ Script Pattern

Every script in `bin/` follows this exact structure:

```php
#!/usr/bin/env php
<?php
include_once __DIR__.'/../../../../include/functions.inc.php';
ini_set('soap.wsdl_cache_enabled', '0');
ini_set('default_socket_timeout', 1000);
ini_set('max_input_time', '0');
ini_set('max_execution_time', '0');
ini_set('display_errors', '1');
ini_set('error_reporting', E_ALL);
if ($_SERVER['argc'] < N) {
    die("Call like {$_SERVER['argv'][0]} <id> <vps>\n...");
}
$master = get_service_master($_SERVER['argv'][1], 'vps', true);
try {
    $params = \Detain\MyAdminHyperv\Plugin::getSoapClientParams();
    $soap = new SoapClient("https://{$master['vps_ip']}/HyperVService/HyperVService.asmx?WSDL", $params);
    $response = $soap->MethodName(['vmId' => $_SERVER['argv'][2], 'hyperVAdmin' => 'Administrator', 'adminPassword' => $master['vps_root']]);
    print_r($response->MethodNameResult);
} catch (Exception $e) {
    echo 'Caught exception: '.$e->getMessage().PHP_EOL;
}
```

## bin/async/ Script Pattern

Async variants in `bin/async/` use ReactPHP (`React\EventLoop`, `React\Socket\Connector`, `Clue\React\Soap`):

```php
$loop = React\EventLoop\Factory::create();
$connector = new React\Socket\Connector(['tls' => ['verify_peer' => false, 'verify_peer_name' => false]]);
$browser = new React\Http\Browser($connector, $loop);
$browser->get($wsdl)->done(
    function ($response) use ($browser, $master) {
        $client = new Clue\React\Soap\Client($browser, (string)$response->getBody());
        $proxy = new Clue\React\Soap\Proxy($client);
        $proxy->MethodName([...])->then(function ($result) { print_r($result); });
    },
    function (Exception $e) { echo 'Error: '.$e->getMessage().PHP_EOL; }
);
$loop->run();
```

## SOAP Operations Reference

| Script | Method | Args |
|--------|--------|------|
| `bin/CreateVM.php` | `CreateVM` | `<id> <name> <hdsize> <ramsize> [template]` |
| `bin/DeleteVM.php` | `DeleteVM` | `<id> <vmId>` |
| `bin/GetVMList.php` | `GetVMList` | `<id>` |
| `bin/GetVM.php` | `GetVM` | `<id> <vmId>` |
| `bin/GetVMState.php` | `GetVMState` | `<id> <vmId>` |
| `bin/TurnON.php` | `TurnON` | `<id> <vmId>` |
| `bin/TurnOff.php` | `TurnOff` | `<id> <vmId>` |
| `bin/ShutDown.php` | `ShutDown` | `<id> <vmId>` |
| `bin/Reboot.php` | `Reboot` | `<id> <vmId>` |
| `bin/Pause.php` | `Pause` | `<id> <vmId>` |
| `bin/Resume.php` | `Resume` | `<id> <vmId>` |
| `bin/UpdateVM.php` | `UpdateVM` | `<id> <vmId> <cpu> <ram>` |
| `bin/SetVMIOPS.php` | `SetVMIOPS` | `<vps_id_or_vzid>` |
| `bin/AddPublicIp.php` | `AddPublicIp` | `<id> <vmId> <ip>` |
| `bin/ResizeVMHardDrive.php` | `ResizeVMHardDrive` | `<id> <vmId>` |
| `bin/CleanUpResources.php` | `CleanUpResources` | `<id>` |

## IOPS Formula (from bin/SetVMIOPS.php)

```php
'minimumOps' => 2 + (2 * $vps_slices),
'maximumOps' => 250 + (100 * $vps_slices),
```

Custom override in `bin/SetVMIOPS_custom.php`: `minimumOps = 5 + (5 * slices)`, `maximumOps = 150 + (50 * slices)`.

## Conventions

- Host server IDs: `423` = Hyperv-dev, `440` = Hyperv1 (used in all script usage strings)
- Auth always: `hyperVAdmin => 'Administrator'`, `adminPassword => $master['vps_root']`
- TLS: always `verify_peer => false`, `verify_peer_name => false` (self-signed certs)
- Tabs for indentation (per `.scrutinizer.yml` coding style)
- camelCase for parameters and properties
- Never commit credentials — `$master['vps_root']` comes from DB via `get_service_master()`

## Testing

```bash
vendor/bin/phpunit tests/ -v
# with coverage (PHP 7.0 / phpdbg):
phpdbg -qrr vendor/bin/phpunit tests/ -v --coverage-clover coverage.xml --whitelist src/
```

- Test class: `tests/PluginTest.php` · namespace `Detain\MyAdminHyperv\Tests\`
- PHPUnit config: `phpunit.xml.dist`
- Static analysis: Scrutinizer CI (`.scrutinizer.yml`) · CodeClimate (`.codeclimate.yml`)

<!-- caliber:managed:pre-commit -->
## Before Committing

**IMPORTANT:** Before every git commit, you MUST ensure Caliber syncs agent configs with the latest code changes.

First, check if the pre-commit hook is already installed:
```bash
grep -q "caliber" .git/hooks/pre-commit 2>/dev/null && echo "hook-active" || echo "no-hook"
```

- If **hook-active**: the hook handles sync automatically — just commit normally. Tell the user: "Caliber will sync your agent configs automatically via the pre-commit hook."
- If **no-hook**: run Caliber manually before committing:
  1. Tell the user: "Caliber: Syncing agent configs with your latest changes..."
  2. Run: `caliber refresh && git add CLAUDE.md .claude/ .cursor/ .cursorrules .github/copilot-instructions.md .github/instructions/ AGENTS.md CALIBER_LEARNINGS.md .agents/ .opencode/ 2>/dev/null`
  3. After it completes, briefly tell the user what Caliber updated. Then proceed with the commit.

If `caliber` is not found, tell the user: "This project uses Caliber for agent config sync. Run /setup-caliber to get set up."
<!-- /caliber:managed:pre-commit -->

<!-- caliber:managed:learnings -->
## Session Learnings

Read `CALIBER_LEARNINGS.md` for patterns and anti-patterns learned from previous sessions.
These are auto-extracted from real tool usage — treat them as project-specific rules.
<!-- /caliber:managed:learnings -->

<!-- caliber:managed:sync -->
## Context Sync

This project uses [Caliber](https://github.com/caliber-ai-org/ai-setup) to keep AI agent configs in sync across Claude Code, Cursor, Copilot, and Codex.
Configs update automatically before each commit via `caliber refresh`.
If the pre-commit hook is not set up, run `/setup-caliber` to configure everything automatically.
<!-- /caliber:managed:sync -->
