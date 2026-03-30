---
name: async-soap-script
description: Creates a ReactPHP async SOAP script in bin/async/ for the myadmin-hyperv-vps plugin. Use when user says 'async version', 'non-blocking', 'ReactPHP', or wants a bin/async/ variant of a SOAP operation. Uses React\EventLoop, React\Socket\Connector (TLS verify disabled), React\Http\Browser, Clue\React\Soap\Client and Proxy. Do NOT use for standard synchronous bin/ scripts — those follow a different pattern in bin/ directly.
---
# async-soap-script

Create a ReactPHP async SOAP script in `bin/async/` for a Hyper-V SOAP operation.

## Critical

- **Include path is one level deeper** than `bin/`: `__DIR__.'/../../../../../include/functions.inc.php'` (five `../` segments, not four).
- **TLS verification must be disabled** on both the `React\Socket\Connector` (`verify_peer`, `verify_peer_name`) — the Hyper-V SOAP endpoint uses a self-signed cert.
- **Never use `Plugin::getSoapClientParams()`** in async scripts — that is synchronous `SoapClient` config. The async path fetches the WSDL via `$browser->get()` and passes the body string to `Clue\React\Soap\Client`.
- **Result property name** mirrors the method name: `CheckVMExists` → `$result->CheckVMExistsResult`. Derive it as `{MethodName}Result`.
- The outer try/catch block wraps `get_service_master()` and the entire event-loop setup. The inner `->done()` error callback handles async failures separately.

## Instructions

1. **Identify the target SOAP method and its parameters.**
   - Find the equivalent synchronous script in `bin/` (e.g. `bin/CheckVMExists.php`) and note the method name and its parameter array.
   - Verify the synchronous script exists before proceeding.

2. **Create the file inside `bin/async/`.**
   - Use the PascalCase SOAP method name, matching the synchronous counterpart exactly — e.g. `bin/async/TurnON.php` for the `TurnON` method, `bin/async/CheckVMExists.php` for `CheckVMExists`.

3. **Write the shebang and bootstrap block** (copy verbatim):
   ```php
   #!/usr/bin/env php
   <?php
   include_once __DIR__.'/../../../../../include/functions.inc.php';
   ini_set('soap.wsdl_cache_enabled', '0');
   ini_set('default_socket_timeout', 1000);
   ini_set('max_input_time', '0');
   ini_set('max_execution_time', '0');
   ini_set('display_errors', '1');
   ini_set('error_reporting', E_ALL);
   ```

4. **Write the argument count check.** Use the same `argc` minimum and die-message style as the sync script:
   ```php
   if ($_SERVER['argc'] < 3) {
       die("Call like {$_SERVER['argv'][0]} <id> <vps>\nwhere <id> is the VPS Master / Host Server ID\nuse 423 for Hyperv-dev and 440 for Hyperv1\n and <vps> is the id of a vps\n");
   }
   ```
   Adjust `< 3` and the message if the method takes fewer/more arguments (e.g. `GetVMList` only needs `<id>`, so `< 2`).

5. **Write the async body inside a try/catch block:**
   ```php
   try {
       $master = get_service_master($_SERVER['argv'][1], 'vps', true);
       $loop = React\EventLoop\Factory::create();
       $connector = new React\Socket\Connector(['tls' => ['verify_peer' => false, 'verify_peer_name' => false]]);
       $browser = new React\Http\Browser($connector, $loop);
       $wsdl = "https://{$master['vps_ip']}/HyperVService/HyperVService.asmx?WSDL";
       $browser->get($wsdl)->done(
           function (Psr\Http\Message\ResponseInterface $response) use ($browser, $master) {
               $client = new Clue\React\Soap\Client($browser, (string)$response->getBody());
               $proxy = new Clue\React\Soap\Proxy($client);
               $proxy->MethodName(['vmId' => $_SERVER['argv'][2], 'hyperVAdmin' => 'Administrator', 'adminPassword' => $master['vps_root']])->then(function ($result) {
                   print_r($result->MethodNameResult);
               });
           },
           function (Exception $e) {
               echo 'Error: ' . $e->getMessage() . PHP_EOL;
           }
       );
       $loop->run();
   } catch (Exception $e) {
       echo 'Caught exception: '.$e->getMessage().PHP_EOL;
   }
   ```
   - Replace `MethodName` with the actual SOAP method (e.g. `CheckVMExists`).
   - Replace `MethodNameResult` with `{MethodName}Result` (e.g. `CheckVMExistsResult`).
   - Replace the `$proxy->MethodName([...])` parameter array with the exact params from the sync script.
   - For methods that return a simple response (not a `*Result` property), use `print_r($result)` instead.

6. **Make the file executable** (using `bin/async/TurnON.php` as the example):
   ```bash
   chmod +x bin/async/TurnON.php
   ```

7. **Verify the script runs without parse errors** (using `bin/async/TurnON.php` as the example):
   ```bash
   php -l bin/async/TurnON.php
   ```

## Examples

**User says:** "Create an async version of TurnON"

**Actions taken:**
1. Read `bin/TurnON.php` — method is `TurnON`, params are `vmId`, `hyperVAdmin`, `adminPassword`, argc < 3.
2. Create `bin/async/TurnON.php`:

```php
#!/usr/bin/env php
<?php
include_once __DIR__.'/../../../../../include/functions.inc.php';
ini_set('soap.wsdl_cache_enabled', '0');
ini_set('default_socket_timeout', 1000);
ini_set('max_input_time', '0');
ini_set('max_execution_time', '0');
ini_set('display_errors', '1');
ini_set('error_reporting', E_ALL);
if ($_SERVER['argc'] < 3) {
    die("Call like {$_SERVER['argv'][0]} <id> <vps>\nwhere <id> is the VPS Master / Host Server ID\nuse 423 for Hyperv-dev and 440 for Hyperv1\n and <vps> is the id of a vps\n");
}
try {
    $master = get_service_master($_SERVER['argv'][1], 'vps', true);
    $loop = React\EventLoop\Factory::create();
    $connector = new React\Socket\Connector(['tls' => ['verify_peer' => false, 'verify_peer_name' => false]]);
    $browser = new React\Http\Browser($connector, $loop);
    $wsdl = "https://{$master['vps_ip']}/HyperVService/HyperVService.asmx?WSDL";
    $browser->get($wsdl)->done(
        function (Psr\Http\Message\ResponseInterface $response) use ($browser, $master) {
            $client = new Clue\React\Soap\Client($browser, (string)$response->getBody());
            $proxy = new Clue\React\Soap\Proxy($client);
            $proxy->TurnON(['vmId' => $_SERVER['argv'][2], 'hyperVAdmin' => 'Administrator', 'adminPassword' => $master['vps_root']])->then(function ($result) {
                print_r($result);
            });
        },
        function (Exception $e) {
            echo 'Error: ' . $e->getMessage() . PHP_EOL;
        }
    );
    $loop->run();
} catch (Exception $e) {
    echo 'Caught exception: '.$e->getMessage().PHP_EOL;
}
```

> Note: `TurnON` does not return a `TurnONResult` property — `print_r($result)` is used instead, matching the sync script.

**Result:** `bin/async/TurnON.php` created and executable.

## Common Issues

- **`include_once` fails with "No such file":** The async path needs five `../` (`__DIR__.'/../../../../../include/functions.inc.php'`), not four. Sync scripts in `bin/` use four.

- **`Class 'React\EventLoop\Factory' not found`:** The ReactPHP packages are not installed. Run `composer install` and verify `react/event-loop`, `react/socket`, `react/http`, and `clue/reactphp-soap` are in `composer.json`.

- **`Error: cURL error 60: SSL certificate problem`:** TLS options on the `Connector` are missing or wrong. Must be exactly `['tls' => ['verify_peer' => false, 'verify_peer_name' => false]]`.

- **`Undefined property: $result->CheckVMExistsResult`:** Result property name is wrong. The convention is `{MethodName}Result`. Check the sync script — if it uses `print_r($response)` rather than `print_r($response->SomeResult)`, the method has no `*Result` property; use `print_r($result)` directly.

- **Script exits immediately with no output:** `$loop->run()` was omitted or placed outside the try block. It must be the last statement inside try, after `$browser->get(...)->done(...)`.
