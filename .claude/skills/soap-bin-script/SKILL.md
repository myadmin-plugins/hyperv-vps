---
name: soap-bin-script
description: Creates a new synchronous SOAP operation script in bin/ following the exact boilerplate from bin/GetVM.php, bin/DeleteVM.php, etc. Use when user says 'add a bin script', 'new SOAP operation', 'create a hyperv command', or adds a new HyperVService method. Generates the ini_set block, argc check, get_service_master() call, getSoapClientParams(), SoapClient instantiation, and exception handler. Do NOT use for async variants (use async-soap-script instead) and do NOT use for Plugin.php method additions.
---
# soap-bin-script

## Critical

- **Never use PDO** — `$master` credentials come exclusively from `get_service_master()`, never from raw DB queries or env vars.
- **Never skip the full `ini_set` block** — all 6 `ini_set` calls must appear verbatim in every script.
- **Always place `hyperVAdmin` and `adminPassword` inside the SOAP params array** — never as separate constructor args.
- **Result key must match the method name** — `$response->GetVMResult` for `GetVM`, `$response->RebootResult` for `Reboot`, etc. (exception: `CreateVM` and similar create operations use `print_r($response)` directly).
- **Do NOT create async variants here** — async scripts belong in `bin/async/` and use a different pattern entirely.

## Instructions

### Step 1 — Identify method signature

Determine:
- **SOAP method name** (e.g., `SetCPUCount`)
- **Extra parameters beyond `<id>`** (e.g., `<vps>`, `<cpuCount>`)
- Whether the response has a typed `Result` property (query/action ops) or returns the raw object (create ops)

Verify the method exists in `https://{host}/HyperVService/HyperVService.asmx?WSDL` before writing the script. Check `bin/WsdlInfo.php` for discovery.

### Step 2 — Create the script file in `bin/`

File name must be the PascalCase SOAP method name inside `bin/` — for example `bin/GetVMState.php` for the `GetVMState` method, or `bin/TurnON.php` for `TurnON`.

Start with the fixed shebang + include + ini_set block. This block is **identical in every script** — do not modify it:

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
```

### Step 3 — Add argc check

Count is `1 (script) + number of positional args`. `<id>` is always first.

- **Host only** (no vps param): `argc < 2`
- **Host + vps**: `argc < 3`
- **Host + vps + 1 extra**: `argc < 4`
- **Host + vps + 2 extras**: `argc < 5`

Usage die message format (match exactly):

```php
// Host only (e.g. GetVMList):
if ($_SERVER['argc'] < 2) {
    die("Call like {$_SERVER['argv'][0]} <id>\nwhere <id> is the VPS Master / Host Server ID\nuse 423 for Hyperv-dev and 440 for Hyperv1\n");
}

// Host + vps (most common):
if ($_SERVER['argc'] < 3) {
    die("Call like {$_SERVER['argv'][0]} <id> <vps>\nwhere <id> is the VPS Master / Host Server ID\nuse 423 for Hyperv-dev and 440 for Hyperv1\n and <vps> is the id of a vps\n");
}

// Host + additional named params:
if ($_SERVER['argc'] < 5) {
    die("Call like {$_SERVER['argv'][0]} <id> <name> <hdsize> <ramsize> [template]\nwhere <id> is the VPS Master / Host Server ID\nuse 423 for Hyperv-dev and 440 for Hyperv1\n");
}
```

### Step 4 — Add host lookup and SOAP call

This output from Step 3 feeds into the try block:

```php
$master = get_service_master($_SERVER['argv'][1], 'vps', true);
try {
    $params = \Detain\MyAdminHyperv\Plugin::getSoapClientParams();
    $soap = new SoapClient("https://{$master['vps_ip']}/HyperVService/HyperVService.asmx?WSDL", $params);
    $response = $soap->MethodName(
        [
        'vmId' => $_SERVER['argv'][2],
        'hyperVAdmin' => 'Administrator',
        'adminPassword' => $master['vps_root']
        ]
    );
    print_r($response->MethodNameResult);
} catch (Exception $e) {
    echo 'Caught exception: '.$e->getMessage().PHP_EOL;
}
```

Rules for the params array:
- `vmId` maps to `$_SERVER['argv'][2]` (the vps arg)
- Additional params use `$_SERVER['argv'][3]`, `[4]`, etc. in order
- `hyperVAdmin` is always `'Administrator'` — hardcoded, never from argv
- `adminPassword` is always `$master['vps_root']`
- For operations with no vmId (e.g., `GetVMList`): omit `vmId`, keep only `hyperVAdmin` + `adminPassword`

Result printing:
- Query/action operations: `print_r($response->{MethodName}Result);`
- Create operations that return the full object: `print_r($response);`

### Step 5 — Verify the script

Run against dev host (id `423`) — using `bin/GetVMState.php` as the example:
```bash
php bin/GetVMState.php 423
# Should print usage die message

php bin/GetVMState.php 423 <vmId>
# Should print SOAP response or 'Caught exception: ...'
```

Verify no PHP parse errors: `php -l bin/GetVMState.php`

## Examples

**User says:** "Add a bin script for the `GetVMState` SOAP method that takes a host id and vps id"

**Actions:**
1. Method name: `GetVMState`, params: `<id> <vps>`, result key: `GetVMStateResult`
2. argc check: `< 3` (host + vps)
3. Create `bin/GetVMState.php`:

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
if ($_SERVER['argc'] < 3) {
    die("Call like {$_SERVER['argv'][0]} <id> <vps>\nwhere <id> is the VPS Master / Host Server ID\nuse 423 for Hyperv-dev and 440 for Hyperv1\n and <vps> is the id of a vps\n");
}
$master = get_service_master($_SERVER['argv'][1], 'vps', true);
try {
    $params = \Detain\MyAdminHyperv\Plugin::getSoapClientParams();
    $soap = new SoapClient("https://{$master['vps_ip']}/HyperVService/HyperVService.asmx?WSDL", $params);
    $response = $soap->GetVMState(
        [
        'vmId' => $_SERVER['argv'][2],
        'hyperVAdmin' => 'Administrator',
        'adminPassword' => $master['vps_root']
        ]
    );
    print_r($response->GetVMStateResult);
} catch (Exception $e) {
    echo 'Caught exception: '.$e->getMessage().PHP_EOL;
}
```

4. Verify: `php -l bin/GetVMState.php` → no errors, `php bin/GetVMState.php 423` → prints usage.

**Result:** `bin/GetVMState.php` created, identical in structure to `bin/GetVM.php`.

## Common Issues

**`PHP Fatal error: Uncaught SoapFault: WSDL`**
- The host is unreachable or the WSDL URL is wrong. Confirm `$master['vps_ip']` is set: add `var_dump($master);` before the try block.
- Confirm `ini_set('soap.wsdl_cache_enabled', '0')` is present — missing it causes stale WSDL cache failures.

**`PHP Fatal error: Call to undefined function get_service_master()`**
- The `include_once` path is wrong. Script must be in `bin/` (four levels from project root). If placed elsewhere, adjust the relative path to `functions.inc.php`.

**`Notice: Undefined property: stdClass::$MethodNameResult`**
- The result key doesn't match. Run `bin/WsdlInfo.php` to inspect exact property names, or use `print_r($response)` temporarily to dump the full response object.

**`Caught exception: Could not connect to host`**
- `default_socket_timeout` too low for slow hosts — it is set to `1000` seconds in the boilerplate, which is correct. If still failing, verify the host server id (`423` = Hyperv-dev, `440` = Hyperv1).

**argc check never triggers / script runs with missing args**
- Ensure the comparison is `< N` not `<= N`. For 2 positional args (`<id> <vps>`), the count is 3 (including script name), so use `$_SERVER['argc'] < 3`.
