#!/usr/bin/env php
<?php
include_once __DIR__.'/../../../../include/functions.inc.php';
ini_set('soap.wsdl_cache_enabled', '0');
ini_set('default_socket_timeout', 1000);
ini_set('max_input_time', '0');
ini_set('max_execution_time', '0');
ini_set('display_errors', '1');
ini_set('error_reporting', E_ALL);
if ($_SERVER['argc'] < 5) {
	die("Call like {$_SERVER['argv'][0]} <id> <name> <hdsize> <ramsize>\nwhere <id> is the VPS Master / Host Server ID\nuse 423 for Hyperv-dev and 440 for Hyperv1\n");
}
$master = get_service_master($_SERVER['argv'][1], 'vps', true);
try {
	$params = \Detain\MyAdminHyperv\Plugin::getSoapClientParams();
	$soap = new SoapClient("https://{$master['vps_ip']}/HyperVService/HyperVService.asmx?WSDL", $params);
	$response = $soap->CreateVM(
		[
		'vmName' => $_SERVER['argv'][2],
		'vhdSize' => $_SERVER['argv'][3],
		'ramSize' => $_SERVER['argv'][4],
		'osToInstall' => 'Windows2012Standard',
		'hyperVAdmin' => 'Administrator',
		'adminPassword' => $master['vps_root']
		]
	);
	print_r($response);
} catch (Exception $e) {
	echo 'Caught exception: '.$e->getMessage().PHP_EOL;
}
/* Returned:
stdClass::__set_state(array(
   'CreateVirtualMachineResult' =>
  stdClass::__set_state(array(
	 'Success' => true,
	 'Status' => $_SERVER['argv'][2],
	 'VmState' => 'Unknown',
  )),
))
*/
