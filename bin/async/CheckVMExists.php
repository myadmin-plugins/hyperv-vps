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
            $proxy->CheckVMExists(['vmId' => $_SERVER['argv'][2], 'hyperVAdmin' => 'Administrator', 'adminPassword' => $master['vps_root']])->then(function ($result) {
                print_r($result->CheckVMExistsResult);
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
