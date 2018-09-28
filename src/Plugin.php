<?php

namespace Detain\MyAdminHyperv;

use Detain\Hyperv\Hyperv;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Class Plugin
 *
 * @package Detain\MyAdminHyperv
 */
class Plugin
{
	public static $name = 'HyperV VPS';
	public static $description = 'Allows selling of HyperV VPS Types.  Microsoft Hyper-V, codenamed Viridian and formerly known as Windows Server Virtualization, is a native hypervisor; it can create virtual machines on x86-64 systems running Windows. Starting with Windows 8, Hyper-V superseded Windows Virtual PC as the hardware virtualization component of the client editions of Windows NT. A server computer running Hyper-V can be configured to expose individual virtual machines to one or more networks.  More info at https://www.microsoft.com/en-us/cloud-platform/server-virtualization';
	public static $help = '';
	public static $module = 'vps';
	public static $type = 'service';

	/**
	 * Plugin constructor.
	 */
	public function __construct()
	{
	}

	/**
	 * @return array
	 */
	public static function getHooks()
	{
		return [
			self::$module.'.settings' => [__CLASS__, 'getSettings'],
			//self::$module.'.activate' => [__CLASS__, 'getActivate'],
			self::$module.'.deactivate' => [__CLASS__, 'getDeactivate'],
			self::$module.'.queue' => [__CLASS__, 'getQueue'],
		];
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getActivate(GenericEvent $event)
	{
		$serviceClass = $event->getSubject();
		if ($event['type'] == get_service_define('HYPERV')) {
			myadmin_log(self::$module, 'info', 'Hyperv Activation', __LINE__, __FILE__);
			$event->stopPropagation();
		}
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getDeactivate(GenericEvent $event)
	{
		if ($event['type'] == get_service_define('HYPERV')) {
			myadmin_log(self::$module, 'info', self::$name.' Deactivation', __LINE__, __FILE__);
			$serviceClass = $event->getSubject();
			$GLOBALS['tf']->history->add(self::$module.'queue', $serviceClass->getId(), 'delete', '', $serviceClass->getCustid());
		}
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getSettings(GenericEvent $event)
	{
		$settings = $event->getSubject();
		$settings->add_text_setting(self::$module, 'Credentials', 'vps_hyperv_password', 'HyperV Administrator Password:', 'Administrative password to login to the HyperV server', $settings->get_setting('VPS_HYPERV_PASSWORD'));
		$settings->add_text_setting(self::$module, 'Slice Costs', 'vps_slice_hyperv_cost', 'HyperV VPS Cost Per Slice:', 'HyperV VPS will cost this much for 1 slice.', $settings->get_setting('VPS_SLICE_HYPERV_COST'));
		$settings->add_select_master(self::$module, 'Default Servers', self::$module, 'new_vps_hyperv_server', 'HyperV NJ Server', NEW_VPS_HYPERV_SERVER, 11, 1);
		$settings->add_dropdown_setting(self::$module, 'Out of Stock', 'outofstock_hyperv', 'Out Of Stock HyperV Secaucus', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_HYPERV'), ['0', '1'], ['No', 'Yes']);
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getQueue(GenericEvent $event)
	{
		if (in_array($event['type'], [get_service_define('HYPERV')])) {
			$vps = $event->getSubject();
			myadmin_log(self::$module, 'info', self::$name.' Queue '.ucwords(str_replace('_', ' ', $vps['action'])).' for VPS '.$vps['vps_hostname'].'(#'.$vps['vps_id'].'/'.$vps['vps_vzid'].')', __LINE__, __FILE__);
			$queue_calls = self::getQueueCalls();
			$method = 'queue'.str_replace('_','',ucwords($vps['action'], '_'));
			if (method_exists(__CLASS__, $method)) {
				myadmin_log(self::$module, 'info', $vps['server_info']['vps_name'].' '.$method.' '.$vps['vps_hostname'].'(#'.$vps['vps_id'].'/'.$vps['vps_vzid'].')', __LINE__, __FILE__);
				call_user_func([__CLASS__, $method], $vps);
			} elseif (!isset($queue_calls[$vps['action']])) {
				myadmin_log(self::$module, 'error', 'Call '.$vps['action'].' for VPS '.$vps['vps_hostname'].'(#'.$vps['vps_id'].'/'.$vps['vps_vzid'].') Does not Exist for '.self::$name, __LINE__, __FILE__);
			} else {
				$calls = $queue_calls[$vps['action']];
				foreach ($calls as $call) {
					myadmin_log(self::$module, 'info', $vps['server_info']['vps_name'].' '.$call.' '.$vps['vps_hostname'].'(#'.$vps['vps_id'].'/'.$vps['vps_vzid'].')', __LINE__, __FILE__);
					try {
						$soap = new \SoapClient(self::getSoapClientUrl($vps['server_info']['vps_ip']), self::getSoapClientParams());
						$response = $soap->$call(self::getSoapCallParams($call, $vps));
					} catch (\SoapFault $e) {
						$msg = $vps['server_info']['vps_name'].' '.$call.' '.$vps['vps_hostname'].'(#'.$vps['vps_id'].'/'.$vps['vps_vzid'].') Caught exception: '.$e->getMessage();
						echo $msg.PHP_EOL;
						myadmin_log(self::$module, 'error', $msg, __LINE__, __FILE__);
						//$event['success'] = FALSE;
					} catch (\Exception $e) {
						$msg = $vps['server_info']['vps_name'].' '.$call.' '.$vps['vps_hostname'].'(#'.$vps['vps_id'].'/'.$vps['vps_vzid'].') Caught exception: '.$e->getMessage();
						echo $msg.PHP_EOL;
						myadmin_log(self::$module, 'error', $msg, __LINE__, __FILE__);
						//$event['success'] = FALSE;
					}
				}
			}
			$event->stopPropagation();
		}
	}

	public static function getQueueCalls()
	{
		return [
			'restart' => ['Reboot'],
			'enable' => ['TurnON'],
			'start' => ['TurnON'],
			'delete' => ['TurnOff'],
			'stop' => ['TurnOff'],
			'destroy' => ['TurnOff', 'DeleteVM'],
			'reinstall_os' => ['TurnOff', 'DeleteVM'],
			'set_slices' => ['TurnOff', 'ResizeVMHardDrive', 'UpdateVM', 'SetVMIOPS', 'TurnON']
		];
	}

	public static function getSoapCallParams($call, $vps)
	{
		if ($call == 'CleanUpResources') {
			return [];
		} elseif ($call == 'ResizeVMHardDrive') {
			return [
				'vmId' => $vps['vps_vzid'],
				'updatedDriveSizeInGigabytes' => 1 * ((VPS_SLICE_HD * $vps['vps_slices']) + $vps['settings']['additional_hd']),
				'hyperVAdminUsername' => 'Administrator',
				'hyperVAdminPassword' => $vps['server_info']['vps_root'],
			];
		} elseif ($call == 'CreateVM') {
			return [
				'vmName' => $vps['vps_hostname'],
				'vhdSize' => 1 * ((VPS_SLICE_HD * $vps['vps_slices']) + $vps['settings']['additional_hd']),
				'ramSize' => 1 * VPS_SLICE_RAM * $vps['vps_slices'],
				'osToInstall' => $vps['vps_os'],
				'hyperVAdmin' => 'Administrator',
				'adminPassword' => $vps['server_info']['vps_root']
			];
		} elseif ($call == 'UpdateVM') {
			return [
				'vmId' => $vps['vps_vzid'],
				'cpuCores' => in_array($vps['vps_custid'], [2773, 8, 2304]) ? ceil($vps['vps_slices'] / 2): ceil($vps['vps_slices'] / 4),
				'ramMB' => 1 * VPS_SLICE_RAM * $vps['vps_slices'],
				'bootFromCD' => false,
				'numLockEnabled' => true,
				'hyperVAdmin' => 'Administrator',
				'adminPassword' => $vps['server_info']['vps_root']
			];
		} elseif ($call == 'SetVMIOPS') {
			return [
				'vmId' => $vps['vps_vzid'],
				'minimumOps' => 5 +  (5 * $vps['vps_slices']),
				'maximumOps' => 250 + (50 * $vps['vps_slices']),
				'adminUsername' => 'Administrator',
				'adminPassword' => $vps['server_info']['vps_root']
			];
		} elseif ($call == 'SetVMAdminPassword') {
			return [
				'adminUser' => 'Administrator',
				'adminPassword' => $vps['server_info']['vps_root'],
				'vmId' => $vps['vps_vzid'],
				'username' => 'Administrator',
				'existingPassword' => VPS_HYPERV_PASSWORD,
				'newPassword' => vps_get_password($vps['vps_id'])
			];
		} elseif ($call == 'AddPublicIp') {
			$db = get_module_db('default');
			$db->query("select vlans_networks from vlans where vlans_id=(select ips_vlan from ips where ips_ip='{$vps['vps_ip']}')");
			$db->next_record(MYSQL_ASSOC);
			function_requirements('ipcalc');
			$ipinfo = ipcalc(str_replace(':', '', $db->Record['vlans_networks']));
			$ip_parameters = [
				'vmId' => $vps['vps_vzid'],
				'ip' => $vps['vps_ip'],
				'defaultGateways' => $ipinfo['hostmin'],
				'subnets' => $ipinfo['netmask'],
				'dns' => ['8.8.8.8', '8.8.4.4'],
				'hyperVAdmin' => 'Administrator',
				'adminPassword' => $vps['server_info']['vps_root']
			];
		} elseif (in_array($call, ['GetVMList'])) {
			return [
				'hyperVAdmin' => 'Administrator',
				'adminPassword' => $vps['server_info']['vps_root']
			];
		} else {
			return [
				'vmId' => $vps['vps_vzid'],
				'hyperVAdmin' => 'Administrator',
				'adminPassword' => $vps['server_info']['vps_root']
			];
		}
	}

	/**
	 * gets the connection URL for the SoapClient
	 *
	 * @param string $address the ip address or domain name of the remote APi server
	 * @return array an array of the connection parameters for Soapclient
	 */
	public static function getSoapClientUrl($address)
	{
		return 'https://'.$address.'/HyperVService/HyperVService.asmx?WSDL';
	}

	/**
	 * gets the preferred connection parameter settings for the SoapClient
	 *
	 * @return array an array of the connection parameters for Soapclient
	 */
	public static function getSoapClientParams()
	{
		return [
			'encoding' => 'UTF-8',
			'verifypeer' => false,
			'verifyhost' => false,
			'soap_version' => SOAP_1_2,
			'trace' => 1,
			'exceptions' => 1,
			'connection_timeout' => 600,
			'stream_context' => stream_context_create([
				'ssl' => [
					'ciphers' => 'RC4-SHA',
					'verify_peer' => false,
					'verify_peer_name' => false
			]])
		];
	}

	public static function queueCreate($vps) {
		$db = get_module_db(self::$module);
		$db2 = get_module_db(self::$module);
		$settings = get_module_settings(self::$module);
		myadmin_log('hyperv', 'info', "HyperV Got Here template {$vps['vps_os']}", __LINE__, __FILE__);
		$vzname = $vps['vps_hostname'];
		myadmin_log('hyperv', 'info', "HyperV Name {$vzname}  VZID {$vps['vzid']} ID {$vps['id']}", __LINE__, __FILE__);
		$memory = $vps['settings']['slice_ram'] * $vps['vps_slices'];
		$diskspace = ($vps['settings']['slice_hd'] * $vps['vps_slices']) + $vps['settings']['additional_hd'];
		$progress = 0;
		$params = \Detain\MyAdminHyperv\Plugin::getSoapClientParams();
		$url = "https://{$vps['server_info']['vps_ip']}/HyperVService/HyperVService.asmx?WSDL";
		$db->query("update vps set vps_server_status='{$progress}' where vps_id={$vps['id']}", __LINE__, __FILE__);
		if (is_uuid($vps['vps_vzid'])) {
			myadmin_log('hyperv', 'info', 'Existing UUID value found, attempting to delete', __LINE__, __FILE__);
			$parameters = [
				'vmId' => $vps['vps_vzid'],
				'hyperVAdmin' => 'Administrator',
				'adminPassword' => $vps['server_info']['vps_root']
			];
			try {
				$soap = new \SoapClient($url, $params);
				$response = $soap->TurnOff($parameters);
				if (isset($response->TurnOffResult->Status)) {
					$status = $response->TurnOffResult->Status;
				} else {
					$status = $response->TurnOffResult->Success;
				}
				myadmin_log('hyperv', 'info', "Response Status: {$status}", __LINE__, __FILE__);
			} catch (\Exception $e) {
				myadmin_log('hyperv', 'warning', 'TurnOff Caught exception: '.$e->getMessage(), __LINE__, __FILE__);
			}
			$progress++;
			$db->query("update vps set vps_server_status='{$progress}' where vps_id={$vps['id']}", __LINE__, __FILE__);
			try {
				$soap = new \SoapClient($url, $params);
				$response = $soap->DeleteVM($parameters);
				if (isset($response->DeleteVMResult->Status)) {
					$status = $response->DeleteVMResult->Status;
				} else {
					$status = $response->DeleteVMResult->Success;
				}
				myadmin_log('hyperv', 'info', "Response Status: {$status}", __LINE__, __FILE__);
			} catch (\Exception $e) {
				myadmin_log('hyperv', 'warning', 'DeleteVM Caught exception: '.$e->getMessage(), __LINE__, __FILE__);
			}
			$progress++;
			$db->query("update vps set vps_server_status='{$progress}' where vps_id={$vps['id']}", __LINE__, __FILE__);
		}
		$start_time = time();
		$create_parameters = [
			'vmName' => $vzname,
			'vhdSize' => $diskspace,
			'ramSize' => $memory,
			'osToInstall' => $vps['vps_os'],
			'hyperVAdmin' => 'Administrator',
			'adminPassword' => $vps['server_info']['vps_root']
		];
		myadmin_log('hyperv', 'info', "CreateVM({$vzname}, {$diskspace}, {$memory}, {$vps['vps_os']})", __LINE__, __FILE__);
		$vps['vzid'] = '';
		try {
			$soap = new \SoapClient($url, $params);
			$response = $soap->CleanUpResources();
			$soap = new \SoapClient($url, $params);
			$response = $soap->CreateVM($create_parameters);
			myadmin_log('hyperv', 'info', json_encode($response->CreateVMResult), __LINE__, __FILE__);
			myadmin_log('hyperv', 'info', $response->CreateVMResult->Status, __LINE__, __FILE__);
			if (trim($response->CreateVMResult->Status) == 'Provider load failure') {
				$progress = 25;
				$db->query("update vps set vps_server_status='{$progress}' where vps_id={$vps['id']}", __LINE__, __FILE__);
				$soap = new \SoapClient($url, $params);
				$response = $soap->CleanUpResources();
				$soap = new \SoapClient($url, $params);
				$response = $soap->CreateVM($create_parameters);
				myadmin_log('hyperv', 'info', json_encode($response->CreateVMResult), __LINE__, __FILE__);
				myadmin_log('hyperv', 'info', $response->CreateVMResult->Status, __LINE__, __FILE__);
			}
			//$response = $soap->CreateVirtualMachine($create_parameters);
		} catch (\Exception $e) {
			myadmin_log('hyperv', 'info', 'Caught exception: '.$e->getMessage(), __LINE__, __FILE__);
			myadmin_log('hyperv', 'info', 'CreateVM Parameters: '.str_replace("\n", '', var_export($create_parameters, true)), __LINE__, __FILE__);
			return false;
		}
		$progress = 50;
		$db->query("update vps set vps_server_status='{$progress}' where vps_id={$vps['id']}", __LINE__, __FILE__);
		myadmin_log('hyperv', 'info', 'finished updating the db', __LINE__, __FILE__);
		$vps['vzid'] = $response->CreateVMResult->Status;
		//$vps['vzid'] = $response->CreateVirtualMachineResult->Status;
		if (mb_strlen($vps['vzid']) != 36) {
			myadmin_log('hyperv', 'info', "id should not be '{$vps['vzid']}' " . mb_strlen($vps['vzid']).' chars long, doing a match up and looking', __LINE__, __FILE__);
			unset($soap);
			$soap = new \SoapClient($url, $params);
			$response = $soap->GetVMList(
				[
					'hyperVAdmin' => 'Administrator',
					'adminPassword' => $vps['server_info']['vps_root']
				]
			);
			if (isset($response->GetVMListResult->VMList->VirtualMachineSummary->Name)) {
				$each = $response->GetVMListResult->VMList;
			} else {
				$each = $response->GetVMListResult->VMList->VirtualMachineSummary;
			}
			foreach ($each as $vm) {
				if ($vm->Name == $vzname) {
					$vps['vzid'] = $vm->VmId;
				}
			}
		}
		$progress++;
		$db->query("update vps set vps_server_status='{$progress}' where vps_id={$vps['id']}", __LINE__, __FILE__);
		if (mb_strlen($vps['vzid']) != 36) {
			myadmin_log('hyperv', 'info', "Invalid ID {$vps['vzid']} Length " . mb_strlen($vps['vzid']), __LINE__, __FILE__);
			return false;
		}
		myadmin_log('hyperv', 'info', "Created HyperV VPS {$vps['id']} : {$vps['vzid']}", __LINE__, __FILE__);
		$db->query("update {$settings['TABLE']} set vps_vzid='" . $db->real_escape($vps['vzid']) . "' where {$settings['PREFIX']}_id='{$vps['vps_id']}'", __LINE__, __FILE__);
		$extra = $vps['extra'];
		$extra['id'] = $vps['vzid'];
		$extra['response'] = $response;
		$update_parameters = [
			'vmId' => $vps['vzid'],
			'cpuCores' => in_array($vps['vps_custid'], [2773, 8, 2304]) ? ceil($vps['vps_slices'] / 2) : ceil($vps['vps_slices'] / 4),
			'ramMB' => 1 * VPS_SLICE_RAM * $vps['vps_slices'],
			'bootFromCD' => false,
			'numLockEnabled' => true,
			'hyperVAdmin' => 'Administrator',
			'adminPassword' => $vps['server_info']['vps_root']
		];
		$iops_parameters = [
			'vmId' => $vps['vzid'],
			'minimumOps' => 2 + (2 * $vps['vps_slices']),
			'maximumOps' => 250 + (100 * $vps['vps_slices']),
			'adminUsername' => 'Administrator',
			'adminPassword' => $vps['server_info']['vps_root']
		];
		myadmin_log('hyperv', 'info', "SetVMIOPS({$vps['vzid']}, " . (5 + (5 * $vps['vps_slices'])).','.(150 + (50 * $vps['vps_slices'])).')', __LINE__, __FILE__);
		try {
			$soap = new \SoapClient($url, $params);
			$response = $soap->SetVMIOPS($iops_parameters);
			myadmin_log('hyperv', 'info', 'SetVMIOPS Response: '.json_encode($response->SetVMIOPSResult), __LINE__, __FILE__);
		} catch (\Exception $e) {
			myadmin_log('hyperv', 'error', 'SetVMIOPS Caught exception: '.$e->getMessage(), __LINE__, __FILE__);
		}
		function_requirements('get_server_ip_info');
		$dbi = get_module_db('default');
		$dbi->query("select vlans_networks from vlans where vlans_id=(select ips_vlan from ips where ips_ip='{$vps['ip']}')");
		$dbi->next_record();
		function_requirements('ipcalc');
		$ipinfo = ipcalc(str_replace(':', '', $dbi->Record['vlans_networks']));
		//$ipinfo = get_server_ip_info($vps['ip']);
		$ip_parameters = [
			'vmId' => $vps['vzid'],
			'ip' => $vps['ip'],
			'defaultGateways' => $ipinfo['hostmin'],
			'subnets' => $ipinfo['netmask'],
			'dns' => ['8.8.8.8', '8.8.4.4'],
			'hyperVAdmin' => 'Administrator',
			'adminPassword' => $vps['server_info']['vps_root']
		];
		/*
		myadmin_log('hyperv', 'info', "AddPublicIp(" . str_replace("\n", "", json_encode($ip_parameters)) . ")", __LINE__, __FILE__);
		try {
			$response = $soap->AddPublicIp($ip_parameters);
		} catch (\Exception $e) {
			myadmin_log('hyperv', 'error', 'Caught exception: '.$e->getMessage(), __LINE__, __FILE__);
			return false;
		}
		*/
		try {
			$soap = new \SoapClient($url, $params);
			$response = $soap->UpdateVM($update_parameters);
			myadmin_log('hyperv', 'info', 'UpdateVM returned '.json_encode($response->UpdateVMResult), __LINE__, __FILE__);
			if (isset($response->UpdateVMResult->Status)) {
				$status = $response->UpdateVMResult->Status;
			} else {
				$status = $response->UpdateVMResult->Success;
			}
			myadmin_log('hyperv', 'info', "UpdateVM Response Status: {$status}", __LINE__, __FILE__);
		} catch (\Exception $e) {
			myadmin_log('hyperv', 'error', 'UpdateVM Caught exception: '.$e->getMessage(), __LINE__, __FILE__);
		}
		$parameters = [
			'vmId' => $vps['vzid'], 'hyperVAdmin' => 'Administrator', 'adminPassword' => $vps['server_info']['vps_root']
		];
		//myadmin_log('hyperv', 'info', "TurnON(" . str_replace("\n", "", json_encode($parameters)) . ")", __LINE__, __FILE__);
		myadmin_log('hyperv', 'info', "TurnON({$vps['vzid']})", __LINE__, __FILE__);
		try {
			$soap = new \SoapClient($url, $params);
			$turnon_response = $soap->TurnON($parameters);
			myadmin_log('hyperv', 'info', "TurnON {$vps['id']} : {$vps['vzid']} Status " . $turnon_response->TurnONResult->Status, __LINE__, __FILE__);
		} catch (\Exception $e) {
			myadmin_log('hyperv', 'error', "TurnON {$vps['id']} : {$vps['vzid']} Caught exception: " . $e->getMessage(), __LINE__, __FILE__);
			return false;
		}
		$progress += 2;
		$db->query("update vps set vps_server_status='{$progress}' where vps_id={$vps['id']}", __LINE__, __FILE__);
		if (preg_match('/Failed to change virtual system state. Error Code= JobStarted . Current state =.*/', trim($turnon_response->TurnONResult->Status))) {
			try {
				$soap = new \SoapClient($url, $params);
				$turnon_response = $soap->TurnON($parameters);
				myadmin_log('hyperv', 'info', "TurnON {$vps['id']} : {$vps['vzid']} Status " . $turnon_response->TurnONResult->Status, __LINE__, __FILE__);
			} catch (\Exception $e) {
				myadmin_log('hyperv', 'error', "TurnON {$vps['id']} : {$vps['vzid']} Caught exception: " . $e->getMessage(), __LINE__, __FILE__);
				return false;
			}
			$progress++;
			$db->query("update vps set vps_server_status='{$progress}' where vps_id={$vps['id']}", __LINE__, __FILE__);
		}
		if (preg_match('/Failed to change virtual system state. Error Code= JobStarted . Current state =.*/', trim($turnon_response->TurnONResult->Status))) {
			myadmin_log('hyperv', 'info', "Bailing on hyperv create ({$vps['id']} : {$vps['vzid']}) - unable to finish", __LINE__, __FILE__);
			$progress = 0;
			$db->query("update vps set vps_server_status='{$progress}' where vps_id={$vps['id']}", __LINE__, __FILE__);
			return false;
		}
		/*
		//myadmin_log('hyperv', 'info', "AddPublicIp(" . str_replace("\n", "", json_encode($ip_parameters)) . ")", __LINE__, __FILE__);
		myadmin_log('hyperv', 'info', "AddPublicIp(" . json_encode($ip_parameters) . ")", __LINE__, __FILE__);
		try {
			$ip_response = $soap->AddPublicIp($ip_parameters);
		} catch (\Exception $e) {
			myadmin_log('hyperv', 'error', 'Caught exception: '.$e->getMessage(), __LINE__, __FILE__);
			return false;
		}
		*/
		// loopuntil it finds there is an ip (any) set (booted up)
		$current_ip = false;
		while ($current_ip === false || $current_ip = '') {
			sleep(20);
			//myadmin_log('hyperv', 'info', "(" . str_replace("\n", "", json_encode($getvm_parameters)) . ")", __LINE__, __FILE__);
			//myadmin_log('hyperv', 'info', "GetVM {$vps['id']} : {$vps['vzid']}", __LINE__, __FILE__);
			try {
				unset($soap);
				$soap = new \SoapClient($url, $params);
				$parameters = [
					'vmId' => $vps['vzid'],
					'hyperVAdmin' => 'Administrator',
					'adminPassword' => $vps['server_info']['vps_root']
				];
				unset($getvm_response);
				$getvm_response = $soap->GetVM($parameters);
				myadmin_log('hyperv', 'info', "GetVM {$vps['id']} : {$vps['vzid']} got {$getvm_response->GetVMResult->Status}", __LINE__, __FILE__);
				if (isset($getvm_response->GetVMResult->IP) && trim($getvm_response->GetVMResult->IP) != '') {
					$current_ip = trim($getvm_response->GetVMResult->IP);
					break;
				}
				/*
				if (in_array(trim($getvm_response->GetVMResult->Status), array('Start', '')) && trim($getvm_response->GetVMResult->IP) == '') {
					$soap = new \SoapClient($url, $params);
					$ip_response = $soap->AddPublicIp($ip_parameters);
				}
				*/
			} catch (\Exception $e) {
				myadmin_log('hyperv', 'warning', "GetVM {$vps['id']} : {$vps['vzid']} Caught exception: " . $e->getMessage(), __LINE__, __FILE__);
				//return false;
			}
			$progress = 70;
			$db->query("update vps set vps_server_status='{$progress}' where vps_id={$vps['id']}", __LINE__, __FILE__);
		}
		if ($current_ip != $vps['ip']) {
			//myadmin_log('hyperv', 'info', "AddPublicIp(" . str_replace("\n", "", json_encode($ip_parameters)) . ")", __LINE__, __FILE__);
			try {
				unset($soap);
				$soap = new \SoapClient($url, $params);
				$ip_response = $soap->AddPublicIp($ip_parameters);
				myadmin_log('hyperv', 'info', "AddPublicIp({$vps['vzid']}, {$vps['ip']}) returned " . json_encode($ip_response->AddPublicIpResult), __LINE__, __FILE__);
			} catch (\Exception $e) {
				myadmin_log('hyperv', 'error', "AddPublicIp {$vps['id']} : {$vps['vzid']} Caught exception: " . $e->getMessage(), __LINE__, __FILE__);
				return false;
			}
		}
		$progress = 75;
		$db->query("update vps set vps_server_status='{$progress}' where vps_id={$vps['id']}", __LINE__, __FILE__);
		$tries = 0;
		$max_tries = 30;
		$finished = false;
		$password_parameters = [
			'adminUser' => 'Administrator',
			'adminPassword' => $vps['server_info']['vps_root'],
			'vmId' => $vps['vzid'],
			'username' => 'Administrator',
			'existingPassword' => 'H0wdy',
			'newPassword' => $vps['origrootpass']
		];
		while ($finished == false && $tries < $max_tries) {
			$exception = false;
			try {
				unset($soap);
				$soap = new \SoapClient($url, $params);
				$pass_respopassword = $soap->SetVMAdminPassword($password_parameters);
				myadmin_log('hyperv', 'info', "SetVMAdminPassword ({$vps['vzid']}, {$vps['origrootpass']}) = " . json_encode($pass_respopassword), __LINE__, __FILE__);
				if (isset($pass_respopassword->SetVMAdminPasswordResult->Status)) {
					$status = trim($pass_respopassword->SetVMAdminPasswordResult->Status);
				} else {
					$status = trim($pass_respopassword->SetVMAdminPasswordResult->Success);
				}
				if ($status == 'Exception has been thrown by the target of an invocation.') {
					$exception = true;
				}
			} catch (\Exception $e) {
				$status = trim($e->getMessage());
				$exception = true;
			}
			if ($progress < 99) {
				$progress++;
				$db->query("update vps set vps_server_status='{$progress}' where vps_id={$vps['id']}", __LINE__, __FILE__);
			}
			$tries++;
			myadmin_log('hyperv', 'warning', "SetVMAdminPassword {$vps['vzid']} (Attempt {$tries}/{$max_tries}) {$status}", __LINE__, __FILE__);
			if ($exception == true || $status == 'Exception has been thrown by the target of an invocation.') {
				$exception = false;
				$pass = generateRandomString(10, 2, 2, 1, 1);
				$password_parameters['newPassword'] = $pass;
				myadmin_log('hyperv', 'warning', "SetVMAdminPassword {$vps['vzid']} Assuming password not complex enough , setting it to a random password, SetVMAdminPassword new pass {$pass}", __LINE__, __FILE__);
				try {
					unset($soap);
					$soap = new \SoapClient($url, $params);
					$pass_respopassword = $soap->SetVMAdminPassword($password_parameters);
					$status = trim($pass_respopassword->SetVMAdminPasswordResult->Success);
					if ($status == 'Exception has been thrown by the target of an invocation.') {
						$exception = true;
					}
				} catch (\Exception $e) {
					$status = trim($e->getMessage());
					$exception = true;
				}
				if ($progress < 99) {
					$progress++;
					$db->query("update vps set vps_server_status='{$progress}' where vps_id={$vps['id']}", __LINE__, __FILE__);
				}
				myadmin_log('hyperv', 'warning', "SetVMAdminPassword {$vps['vzid']} (Attempt {$tries}/{$max_tries}) {$status}", __LINE__, __FILE__);
			}
			if ($status == 'Access is denied.') {
				myadmin_log('hyperv', 'info', "SetVMAdminPassword (Attempt {$tries}/{$max_tries}) Invalid Template Password?", __LINE__, __FILE__);
			}
			if ($exception == true || ($status == 'The network path was not found.')) {
				//myadmin_log('hyperv', 'info', "SetVMAdminPassword (Attempt {$tries}/{$max_tries}) Sleeping", __LINE__, __FILE__);
				sleep(10);
				myadmin_log('hyperv', 'info', "SetVMAdminPassword {$vps['vzid']} (Attempt {$tries}/{$max_tries}) Finished Sleeping", __LINE__, __FILE__);
				continue;
			} elseif (isset($pass_respopassword) && $pass_respopassword->SetVMAdminPasswordResult->Success == 1) {
				myadmin_log('hyperv', 'info', "SetVMAdminPassword {$vps['vzid']} Successful", __LINE__, __FILE__);
				$finished = true;
				if ($pass != $vps['origrootpass']) {
					$db->query(
					make_insert_query('history_log', [
						'history_id' => null,
						'history_sid' => '',
						'history_timestamp' => mysql_now(),
						'history_creator' => $vps['vps_custid'],
						'history_owner' => $vps['vps_custid'],
						'history_section' => $settings['PREFIX'],
						'history_type' => 'password',
						'history_new_value' => $vps['vps_id'],
						'history_old_value' => $pass
					]), __LINE__, __FILE__
				);
				}
				vps_windows_welcome_email($vps['vps_id'], self::$module);
				continue;
			}
		}
		$password_parameters['username'] = 'int-user';
		try {
			unset($soap);
			$soap = new \SoapClient($url, $params);
			$pass_respopassword = $soap->SetVMAdminPassword($password_parameters);
			myadmin_log('hyperv', 'info', "SetVMAdminPassword ({$vps['vzid']}, {$vps['origrootpass']}) = " . json_encode($pass_respopassword), __LINE__, __FILE__);
			if (isset($pass_respopassword->SetVMAdminPasswordResult->Status)) {
				$status = trim($pass_respopassword->SetVMAdminPasswordResult->Status);
			} else {
				$status = trim($pass_respopassword->SetVMAdminPasswordResult->Success);
			}
		} catch (\Exception $e) {
			$status = trim($e->getMessage());
		}
		myadmin_log('hyperv', 'info', "Start Time {$start_time}", __LINE__, __FILE__);
		$end_time = time();
		myadmin_log('hyperv', 'info', "End Time {$end_time}", __LINE__, __FILE__);
		$install_time = $end_time - $start_time;
		myadmin_log('hyperv', 'info', "Install Time {$install_time}", __LINE__, __FILE__);
		$progress = 100;
		$q1 = "update vps set vps_server_status='{$progress}' where vps_id={$vps['id']}";
		$q2 = "update vps_master_details set vps_last_install_time={$install_time} where vps_id={$vps['server_info']['vps_id']}";
		myadmin_log('hyperv', 'info', $q1, __LINE__, __FILE__);
		$db2->query($q1, __LINE__, __FILE__);
		myadmin_log('hyperv', 'info', $q2, __LINE__, __FILE__);
		$db2->query($q2, __LINE__, __FILE__);
		$db->query("update {$settings['TABLE']} set vps_os='" . $db->real_escape($vps['vps_os']) . "', vps_extra='" . $db->real_escape(myadmin_stringify($extra)) . "' where {$settings['PREFIX']}_id='{$vps[$settings['PREFIX'].'_id']}'", __LINE__, __FILE__);
		return true;
	}
}
