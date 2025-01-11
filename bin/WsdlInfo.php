#!/usr/bin/env php
<?php
include_once __DIR__.'/../../../../include/functions.inc.php';
ini_set('soap.wsdl_cache_enabled', '0');
ini_set('default_socket_timeout', 1000);
ini_set('max_input_time', '0');
ini_set('max_execution_time', '0');
ini_set('display_errors', '1');
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

if ($_SERVER['argc'] < 2) {
    die("Call like {$_SERVER['argv'][0]} <id>\nwhere <id> is the VPS Master / Host Server ID\nuse 423 for Hyperv-dev and 440 for Hyperv1\n");
}
$master = get_service_master($_SERVER['argv'][1], 'vps', true);
try {
    $connector = new React\Socket\Connector(array(
        'tls' => array(
            'verify_peer' => false,
            'verify_peer_name' => false
        )
    ));
    $browser = new React\Http\Browser($connector);
    $wsdl = "https://{$master['vps_ip']}/HyperVService/HyperVService.asmx?WSDL";
    $params = \Detain\MyAdminHyperv\Plugin::getSoapClientParams();
    $browser->get($wsdl)->done(
        function (Psr\Http\Message\ResponseInterface $response) use ($browser) {
            $client = new Clue\React\Soap\Client($browser, (string)$response->getBody());
            $functions = $client->getFunctions();
            sort($functions);
            $functions = array_unique($functions);
            $functions = implode("\n", $functions);
            $types = implode("\n", $client->getTypes());
            /*
            file_put_contents('functions.txt', $functions);
            file_put_contents('types.txt', $types);
            preg_match_all('/^string (?P<var>.*)$/muU', $input_lines, $output_array);
            preg_match_all('/^struct (?P<var>\S*) {\n(?P<vars>(^.*$\n)*)}/muU', $input_lines, $output_array);
            preg_match_all('/^\s+(?P<type>\S+)\s+(?P<var>\S+);$/muU', $input_lines, $output_array);
            if (preg_match_all('/^(?P<response>\S*) (?P<function>\S*)\((?P<request>\S*) (?P<var>\$parameters)\)/muU', $functions, $matches)) {
                foreach ($matches['function'] as $idx => $function) {
                }
                exit;                    
            } else {
                echo "no matches\n";                
            }*/            
            echo 'Functions:' . PHP_EOL .
            implode(PHP_EOL, $functions) . PHP_EOL .
            PHP_EOL .
            'Types:' . PHP_EOL .
            implode(PHP_EOL, $types) . PHP_EOL;
        },
        function (Exception $e) {
            echo 'Error: ' . $e->getMessage() . PHP_EOL;
        }
    );
} catch (Exception $e) {
    echo 'Caught exception: '.$e->getMessage().PHP_EOL;
}
