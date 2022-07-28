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
    $response = $soap->TurnON(
        [
        'vmId' => $_SERVER['argv'][2],
        'hyperVAdmin' => 'Administrator',
        'adminPassword' => $master['vps_root']
        ]
    );
    print_r($response);
} catch (Exception $e) {
    echo 'Caught exception: '.$e->getMessage().PHP_EOL;
}
