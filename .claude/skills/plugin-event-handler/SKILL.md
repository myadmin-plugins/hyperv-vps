---
name: plugin-event-handler
description: Adds or modifies event handler methods in src/Plugin.php for the Detain\MyAdminHyperv namespace using Symfony GenericEvent. Use when user says 'add event', 'hook into', 'register listener', 'plugin event', or extends Plugin.php functionality with new event subscriptions. References getSoapClientParams() and getHooks() patterns. Do NOT use for standalone bin/ scripts or bin/async/ scripts.
---
# Plugin Event Handler

## Critical

- ALL event handlers MUST be `public static function` — never instance methods.
- ALL event handlers MUST accept exactly one parameter: `GenericEvent $event` (fully qualified: `Symfony\Component\EventDispatcher\GenericEvent`).
- Every new handler MUST be registered in `getHooks()` or it will never fire.
- Event names MUST follow the `{module}.{action}` pattern (e.g., `vps.activate`, `vps.deactivate`). The module is `self::$module` = `'vps'`.
- Guard HYPERV-specific logic with `if ($event['type'] == get_service_define('HYPERV'))` before any action.
- NEVER remove or alter the `use Symfony\Component\EventDispatcher\GenericEvent;` import at the top of `src/Plugin.php`.
- Run `phpunit` after every change — tests verify method signatures and hook registration.

## Instructions

1. **Read `src/Plugin.php`** to understand the current handler list before adding anything.
   ```
   // Verify the file top contains:
   use Symfony\Component\EventDispatcher\GenericEvent;
   ```
   Confirm the import exists before proceeding.

2. **Register the new hook in `getHooks()`.**
   Open `src/Plugin.php` and add an entry to the returned array inside `getHooks()`:
   ```php
   public static function getHooks()
   {
       return [
           self::$module.'.settings'   => [__CLASS__, 'getSettings'],
           self::$module.'.deactivate' => [__CLASS__, 'getDeactivate'],
           self::$module.'.activate'   => [__CLASS__, 'getActivate'],
           self::$module.'.myevent'    => [__CLASS__, 'getMyEvent'], // ← add here
       ];
   }
   ```
   Verify: the key is `self::$module.'.eventname'` and the value is `[__CLASS__, 'getMyEvent']`.

3. **Add the handler method** immediately after the last existing handler in `src/Plugin.php`.
   Use this exact signature and structure:
   ```php
   /**
    * @param \Symfony\Component\EventDispatcher\GenericEvent $event
    */
   public static function getMyEvent(GenericEvent $event)
   {
       if ($event['type'] == get_service_define('HYPERV')) {
           $serviceClass = $event->getSubject();
           myadmin_log(self::$module, 'info', self::$name.' MyEvent', __LINE__, __FILE__, self::$module, $serviceClass->getId());
           // ... handler logic ...
           $event->stopPropagation();
       }
   }
   ```
   - `$event->getSubject()` returns the service object (for activate/deactivate) or the `$serviceInfo` array (for queue-style events).
   - `$event['type']` holds the VPS type integer — always guard with `get_service_define('HYPERV')`.
   - Call `$event->stopPropagation()` only when this plugin fully handled the event and other listeners should not run.
   - For queue-style handlers, retrieve `$settings = get_module_settings(self::$module)` and log with the service ID `$serviceInfo[$settings['PREFIX'].'_id']`.

4. **If the handler needs SOAP**, use `getSoapClientParams()` and `getSoapClientUrl()`:
   ```php
   $soap = new \SoapClient(
       self::getSoapClientUrl($serviceInfo['server_info']['vps_ip']),
       self::getSoapClientParams()
   );
   $response = $soap->MethodName(self::getSoapCallParams('MethodName', $serviceInfo));
   ```
   Never construct SOAP params inline — always route through `getSoapCallParams()`.

5. **If the handler needs DB access**, use the module DB helper — never PDO:
   ```php
   $db = get_module_db(self::$module);
   $db->query("SELECT * FROM vps WHERE vps_id = {$db->real_escape($id)}", __LINE__, __FILE__);
   ```

6. **Run tests** to verify the new handler is wired correctly:
   ```bash
   phpunit
   ```
   The test suite checks that every method listed in `getHooks()` exists on the class and has the correct `GenericEvent` signature.

## Examples

**User says:** "Add a hook that logs when a HyperV VM is activated."

**Actions taken:**

Step 2 — add to `getHooks()`:
```php
self::$module.'.activate' => [__CLASS__, 'getActivate'],
```

Step 3 — add the handler (mirrors the existing commented-out `getActivate` pattern):
```php
/**
 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
 */
public static function getActivate(GenericEvent $event)
{
    $serviceClass = $event->getSubject();
    if ($event['type'] == get_service_define('HYPERV')) {
        myadmin_log(self::$module, 'info', 'Hyperv Activation', __LINE__, __FILE__, self::$module, $serviceClass->getId());
        $event->stopPropagation();
    }
}
```

Step 6 — run `phpunit`. The `getHooksMethodsExistOnClass` and `getActivateMethodSignature` tests pass.

**Result:** `vps.activate` fires `Plugin::getActivate()` whenever a VPS activation event is dispatched for a HyperV service type.

## Common Issues

**Error: `getHooksMethodsExistOnClass` test fails — "Method 'getMyEvent' should exist on Plugin class"**
You registered the hook in `getHooks()` but forgot to add the method body. Add the `public static function getMyEvent(GenericEvent $event)` method to the class.

**Error: `getActivateMethodSignature` test fails — "should be 'Symfony\\Component\\EventDispatcher\\GenericEvent'"**
The parameter type hint is missing or wrong. Change the signature to exactly:
```php
public static function getMyEvent(GenericEvent $event)
```
and confirm `use Symfony\Component\EventDispatcher\GenericEvent;` is present at the top of the file.

**Error: `hooksUseModulePrefixForEventNames` test fails**
Your hook key does not start with `vps.`. Change `'myevent'` to `self::$module.'.myevent'`.

**Error: Hook fires but `get_service_define('HYPERV')` is undefined**
The functions bootstrap has not been loaded. This only happens in isolation (unit tests). In production, `include/functions.inc.php` defines it. In tests, define it yourself at the top of the test if needed:
```php
if (!defined('HYPERV')) { define('HYPERV', 20); }
```

**Error: SOAP call fails with `SoapFault: Could not connect to host`**
The `getSoapClientParams()` return value disables peer verification (`verifypeer => false`, `verifyhost => false`) and sets a 600-second timeout. If connecting fails anyway, check that `$serviceInfo['server_info']['vps_ip']` is populated from `get_service_master()` before the SOAP call.
