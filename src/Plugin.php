<?php

namespace Detain\MyAdminHyperv;

use Detain\Hyperv\Hyperv;
use Symfony\Component\EventDispatcher\GenericEvent;

class Plugin {

	public static $name = 'Hyperv Vps';
	public static $description = 'Allows selling of Hyperv Server and VPS License Types.  More info at https://www.netenberg.com/hyperv.php';
	public static $help = 'It provides more than one million end users the ability to quickly install dozens of the leading open source content management systems into their web space.  	Must have a pre-existing cPanel license with cPanelDirect to purchase a hyperv license. Allow 10 minutes for activation.';
	public static $module = 'vps';
	public static $type = 'service';


	public function __construct() {
	}

	public static function getHooks() {
		return [
			self::$module.'.settings' => [__CLASS__, 'getSettings'],
		];
	}

	public static function getActivate(GenericEvent $event) {
		$license = $event->getSubject();
		if ($event['category'] == SERVICE_TYPES_FANTASTICO) {
			myadmin_log(self::$module, 'info', 'Hyperv Activation', __LINE__, __FILE__);
			function_requirements('activate_hyperv');
			activate_hyperv($license->get_ip(), $event['field1']);
			$event->stopPropagation();
		}
	}

	public static function getChangeIp(GenericEvent $event) {
		if ($event['category'] == SERVICE_TYPES_FANTASTICO) {
			$license = $event->getSubject();
			$settings = get_module_settings(self::$module);
			$hyperv = new Hyperv(FANTASTICO_USERNAME, FANTASTICO_PASSWORD);
			myadmin_log(self::$module, 'info', "IP Change - (OLD:".$license->get_ip().") (NEW:{$event['newip']})", __LINE__, __FILE__);
			$result = $hyperv->editIp($license->get_ip(), $event['newip']);
			if (isset($result['faultcode'])) {
				myadmin_log(self::$module, 'error', 'Hyperv editIp('.$license->get_ip().', '.$event['newip'].') returned Fault '.$result['faultcode'].': '.$result['fault'], __LINE__, __FILE__);
				$event['status'] = 'error';
				$event['status_text'] = 'Error Code '.$result['faultcode'].': '.$result['fault'];
			} else {
				$GLOBALS['tf']->history->add($settings['TABLE'], 'change_ip', $event['newip'], $license->get_ip());
				$license->set_ip($event['newip'])->save();
				$event['status'] = 'ok';
				$event['status_text'] = 'The IP Address has been changed.';
			}
			$event->stopPropagation();
		}
	}

	public static function getMenu(GenericEvent $event) {
		$menu = $event->getSubject();
		if ($GLOBALS['tf']->ima == 'admin') {
			$menu->add_link(self::$module, 'choice=none.reusable_hyperv', 'icons/database_warning_48.png', 'ReUsable Hyperv Licenses');
			$menu->add_link(self::$module, 'choice=none.hyperv_list', 'icons/database_warning_48.png', 'Hyperv Licenses Breakdown');
			$menu->add_link(self::$module.'api', 'choice=none.hyperv_licenses_list', 'whm/createacct.gif', 'List all Hyperv Licenses');
		}
	}

	public static function getRequirements(GenericEvent $event) {
		$loader = $event->getSubject();
		$loader->add_requirement('crud_hyperv_list', '/../vendor/detain/crud/src/crud/crud_hyperv_list.php');
		$loader->add_requirement('crud_reusable_hyperv', '/../vendor/detain/crud/src/crud/crud_reusable_hyperv.php');
		$loader->add_requirement('get_hyperv_licenses', '/../vendor/detain/myadmin-hyperv-vps/src/hyperv.inc.php');
		$loader->add_requirement('get_hyperv_list', '/../vendor/detain/myadmin-hyperv-vps/src/hyperv.inc.php');
		$loader->add_requirement('hyperv_licenses_list', '/../vendor/detain/myadmin-hyperv-vps/src/hyperv_licenses_list.php');
		$loader->add_requirement('hyperv_list', '/../vendor/detain/myadmin-hyperv-vps/src/hyperv_list.php');
		$loader->add_requirement('get_available_hyperv', '/../vendor/detain/myadmin-hyperv-vps/src/hyperv.inc.php');
		$loader->add_requirement('activate_hyperv', '/../vendor/detain/myadmin-hyperv-vps/src/hyperv.inc.php');
		$loader->add_requirement('get_reusable_hyperv', '/../vendor/detain/myadmin-hyperv-vps/src/hyperv.inc.php');
		$loader->add_requirement('reusable_hyperv', '/../vendor/detain/myadmin-hyperv-vps/src/reusable_hyperv.php');
		$loader->add_requirement('class.Hyperv', '/../vendor/detain/hyperv-vps/src/Hyperv.php');
		$loader->add_requirement('vps_add_hyperv', '/vps/addons/vps_add_hyperv.php');
	}

	public static function getSettings(GenericEvent $event) {
		$settings = $event->getSubject();
		$settings->add_text_setting(self::$module, 'Credentials', 'vps_hyperv_password', 'HyperV Administrator Password:', 'Administrative password to login to the HyperV server', $settings->get_setting('VPS_HYPERV_PASSWORD'));
		$settings->add_text_setting(self::$module, 'Slice Costs', 'vps_slice_hyperv_cost', 'HyperV VPS Cost Per Slice:', 'HyperV VPS will cost this much for 1 slice.', $settings->get_setting('VPS_SLICE_HYPERV_COST'));
		$settings->add_select_master(self::$module, 'Default Servers', self::$module, 'new_vps_hyperv_server', 'HyperV NJ Server', NEW_VPS_HYPERV_SERVER, 11, 1);
		$settings->add_dropdown_setting(self::$module, 'Out of Stock', 'outofstock_hyperv', 'Out Of Stock HyperV Secaucus', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_HYPERV'), array('0', '1'), array('No', 'Yes',));
	}

}
