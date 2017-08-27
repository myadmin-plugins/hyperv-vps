<?php

namespace Detain\MyAdminHyperv;

use Detain\Hyperv\Hyperv;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Class Plugin
 *
 * @package Detain\MyAdminHyperv
 */
class Plugin {

	public static $name = 'HyperV VPS';
	public static $description = 'Allows selling of HyperV VPS Types.  Microsoft Hyper-V, codenamed Viridian[1] and formerly known as Windows Server Virtualization, is a native hypervisor; it can create virtual machines on x86-64 systems running Windows.[2] Starting with Windows 8, Hyper-V superseded Windows Virtual PC as the hardware virtualization component of the client editions of Windows NT. A server computer running Hyper-V can be configured to expose individual virtual machines to one or more networks.  More info at https://www.microsoft.com/en-us/cloud-platform/server-virtualization';
	public static $help = '';
	public static $module = 'vps';
	public static $type = 'service';

	/**
	 * Plugin constructor.
	 */
	public function __construct() {
	}

	/**
	 * @return array
	 */
	public static function getHooks() {
		return [
			self::$module.'.settings' => [__CLASS__, 'getSettings'],
			//self::$module.'.activate' => [__CLASS__, 'getActivate'],
			self::$module.'.deactivate' => [__CLASS__, 'getDeactivate']
		];
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getActivate(GenericEvent $event) {
		$serviceClass = $event->getSubject();
		if ($event['type'] == get_service_define('HYPERV')) {
			myadmin_log(self::$module, 'info', 'Hyperv Activation', __LINE__, __FILE__);
			$event->stopPropagation();
		}
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getDeactivate(GenericEvent $event) {
		if ($event['type'] == get_service_define('HYPERV')) {
			myadmin_log(self::$module, 'info', self::$name.' Deactivation', __LINE__, __FILE__);
			$serviceClass = $event->getSubject();
			$GLOBALS['tf']->history->add(self::$module.'queue', $serviceClass->getId(), 'delete', '', $serviceClass->getCustid());
		}
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getChangeIp(GenericEvent $event) {
		if ($event['type'] == get_service_define('HYPERV')) {
			$serviceClass = $event->getSubject();
			$settings = get_module_settings(self::$module);
			$hyperv = new Hyperv(FANTASTICO_USERNAME, FANTASTICO_PASSWORD);
			myadmin_log(self::$module, 'info', 'IP Change - (OLD:' .$serviceClass->getIp().") (NEW:{$event['newip']})", __LINE__, __FILE__);
			$result = $hyperv->editIp($serviceClass->getIp(), $event['newip']);
			if (isset($result['faultcode'])) {
				myadmin_log(self::$module, 'error', 'Hyperv editIp('.$serviceClass->getIp().', '.$event['newip'].') returned Fault '.$result['faultcode'].': '.$result['fault'], __LINE__, __FILE__);
				$event['status'] = 'error';
				$event['status_text'] = 'Error Code '.$result['faultcode'].': '.$result['fault'];
			} else {
				$GLOBALS['tf']->history->add($settings['TABLE'], 'change_ip', $event['newip'], $serviceClass->getIp());
				$serviceClass->set_ip($event['newip'])->save();
				$event['status'] = 'ok';
				$event['status_text'] = 'The IP Address has been changed.';
			}
			$event->stopPropagation();
		}
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getMenu(GenericEvent $event) {
		$menu = $event->getSubject();
		if ($GLOBALS['tf']->ima == 'admin') {
			$menu->add_link(self::$module, 'choice=none.reusable_hyperv', 'images/icons/database_warning_48.png', 'ReUsable Hyperv Licenses');
			$menu->add_link(self::$module, 'choice=none.hyperv_list', 'images/icons/database_warning_48.png', 'Hyperv Licenses Breakdown');
			$menu->add_link(self::$module.'api', 'choice=none.hyperv_licenses_list', '/images/whm/createacct.gif', 'List all Hyperv Licenses');
		}
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getRequirements(GenericEvent $event) {
		$loader = $event->getSubject();
		$loader->add_page_requirement('crud_hyperv_list', '/../vendor/detain/crud/src/crud/crud_hyperv_list.php');
		$loader->add_page_requirement('crud_reusable_hyperv', '/../vendor/detain/crud/src/crud/crud_reusable_hyperv.php');
		$loader->add_requirement('get_hyperv_licenses', '/../vendor/detain/myadmin-hyperv-vps/src/hyperv.inc.php');
		$loader->add_requirement('get_hyperv_list', '/../vendor/detain/myadmin-hyperv-vps/src/hyperv.inc.php');
		$loader->add_page_requirement('hyperv_licenses_list', '/../vendor/detain/myadmin-hyperv-vps/src/hyperv_licenses_list.php');
		$loader->add_page_requirement('hyperv_list', '/../vendor/detain/myadmin-hyperv-vps/src/hyperv_list.php');
		$loader->add_requirement('get_available_hyperv', '/../vendor/detain/myadmin-hyperv-vps/src/hyperv.inc.php');
		$loader->add_requirement('activate_hyperv', '/../vendor/detain/myadmin-hyperv-vps/src/hyperv.inc.php');
		$loader->add_requirement('get_reusable_hyperv', '/../vendor/detain/myadmin-hyperv-vps/src/hyperv.inc.php');
		$loader->add_page_requirement('reusable_hyperv', '/../vendor/detain/myadmin-hyperv-vps/src/reusable_hyperv.php');
		$loader->add_requirement('class.Hyperv', '/../vendor/detain/hyperv-vps/src/Hyperv.php');
		$loader->add_page_requirement('vps_add_hyperv', '/vps/addons/vps_add_hyperv.php');
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getSettings(GenericEvent $event) {
		$settings = $event->getSubject();
		$settings->add_text_setting(self::$module, 'Credentials', 'vps_hyperv_password', 'HyperV Administrator Password:', 'Administrative password to login to the HyperV server', $settings->get_setting('VPS_HYPERV_PASSWORD'));
		$settings->add_text_setting(self::$module, 'Slice Costs', 'vps_slice_hyperv_cost', 'HyperV VPS Cost Per Slice:', 'HyperV VPS will cost this much for 1 slice.', $settings->get_setting('VPS_SLICE_HYPERV_COST'));
		$settings->add_select_master(self::$module, 'Default Servers', self::$module, 'new_vps_hyperv_server', 'HyperV NJ Server', NEW_VPS_HYPERV_SERVER, 11, 1);
		$settings->add_dropdown_setting(self::$module, 'Out of Stock', 'outofstock_hyperv', 'Out Of Stock HyperV Secaucus', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_HYPERV'), ['0', '1'], ['No', 'Yes']);
	}

}
