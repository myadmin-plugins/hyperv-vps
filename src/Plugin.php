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
			self::$module.'.deactivate' => [__CLASS__, 'getDeactivate'],
			self::$module.'.queue_enable' => [__CLASS__, 'getQueueEnable'],
			self::$module.'.queue_destroy' => [__CLASS__, 'getQueueDestroy'],
			self::$module.'.queue_delete' => [__CLASS__, 'getQueueDelete'],
			self::$module.'.queue_reinstall_os' => [__CLASS__, 'getQueueReinstallOs'],
			self::$module.'.queue_update_hdsize' => [__CLASS__, 'getQueueUpdateHdsize'],
			self::$module.'.queue_enable_cd' => [__CLASS__, 'getQueueEnableCd'],
			self::$module.'.queue_disable_cd' => [__CLASS__, 'getQueueDisableCd'],
			self::$module.'.queue_insert_cd' => [__CLASS__, 'getQueueInsertCd'],
			self::$module.'.queue_eject_cd' => [__CLASS__, 'getQueueEjectCd'],
			self::$module.'.queue_start' => [__CLASS__, 'getQueueStart'],
			self::$module.'.queue_stop' => [__CLASS__, 'getQueueStop'],
			self::$module.'.queue_restart' => [__CLASS__, 'getQueueRestart'],
			self::$module.'.queue_setup_vnc' => [__CLASS__, 'getQueueSetupVnc'],
			self::$module.'.queue_reset_password' => [__CLASS__, 'getQueueResetPassword'],
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

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getQueueEnable(GenericEvent $event) {
		if (in_array($event['type'], [get_service_define('KVM_LINUX'), get_service_define('KVM_WINDOWS'), get_service_define('CLOUD_KVM_LINUX'), get_service_define('CLOUD_KVM_WINDOWS')])) {
			myadmin_log(self::$module, 'info', self::$name.' Queue Enable', __LINE__, __FILE__);
			$serviceClass = $event->getSubject();
			$smarty = new \TFSmarty();
			$smarty->assign([
				'vps_id' => $serviceClass->getId(),
				'vps_vzid' => is_numeric($serviceClass->getVzid()) ? (in_array($event['type'], [get_service_define('KVM_WINDOWS'), get_service_define('CLOUD_KVM_WINDOWS')]) ? 'windows'.$serviceClass->getVzid() : 'linux'.$serviceClass->getVzid()) : $serviceClass->getVzid(),
				'email' => $GLOBALS['tf']->accounts->cross_reference($serviceClass->getCustid()),
				'domain' => DOMAIN,
				'param1' => $event['param1']
			]);
			echo $smarty->fetch(__DIR__.'/../templates/enable.sh.tpl');
			$event->stopPropagation();
		}
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getQueueDestroy(GenericEvent $event) {
		if (in_array($event['type'], [get_service_define('KVM_LINUX'), get_service_define('KVM_WINDOWS'), get_service_define('CLOUD_KVM_LINUX'), get_service_define('CLOUD_KVM_WINDOWS')])) {
			myadmin_log(self::$module, 'info', self::$name.' Queue Destroy', __LINE__, __FILE__);
			$serviceClass = $event->getSubject();
			$smarty = new \TFSmarty();
			$smarty->assign([
				'vps_id' => $serviceClass->getId(),
				'vps_vzid' => is_numeric($serviceClass->getVzid()) ? (in_array($event['type'], [get_service_define('KVM_WINDOWS'), get_service_define('CLOUD_KVM_WINDOWS')]) ? 'windows'.$serviceClass->getVzid() : 'linux'.$serviceClass->getVzid()) : $serviceClass->getVzid(),
				'email' => $GLOBALS['tf']->accounts->cross_reference($serviceClass->getCustid()),
				'domain' => DOMAIN,
				'param1' => $event['param1']
			]);
			echo $smarty->fetch(__DIR__.'/../templates/destroy.sh.tpl');
			$event->stopPropagation();
		}
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getQueueDelete(GenericEvent $event) {
		if (in_array($event['type'], [get_service_define('KVM_LINUX'), get_service_define('KVM_WINDOWS'), get_service_define('CLOUD_KVM_LINUX'), get_service_define('CLOUD_KVM_WINDOWS')])) {
			myadmin_log(self::$module, 'info', self::$name.' Queue Delete', __LINE__, __FILE__);
			$serviceClass = $event->getSubject();
			$smarty = new \TFSmarty();
			$smarty->assign([
				'vps_id' => $serviceClass->getId(),
				'vps_vzid' => is_numeric($serviceClass->getVzid()) ? (in_array($event['type'], [get_service_define('KVM_WINDOWS'), get_service_define('CLOUD_KVM_WINDOWS')]) ? 'windows'.$serviceClass->getVzid() : 'linux'.$serviceClass->getVzid()) : $serviceClass->getVzid(),
				'email' => $GLOBALS['tf']->accounts->cross_reference($serviceClass->getCustid()),
				'domain' => DOMAIN,
				'param1' => $event['param1']
			]);
			echo $smarty->fetch(__DIR__.'/../templates/delete.sh.tpl');
			$event->stopPropagation();
		}
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getQueueReinstallOsupdateHdsize(GenericEvent $event) {
		if (in_array($event['type'], [get_service_define('KVM_LINUX'), get_service_define('KVM_WINDOWS'), get_service_define('CLOUD_KVM_LINUX'), get_service_define('CLOUD_KVM_WINDOWS')])) {
			myadmin_log(self::$module, 'info', self::$name.' Queue Reinstall Osupdate Hdsize', __LINE__, __FILE__);
			$serviceClass = $event->getSubject();
			$smarty = new \TFSmarty();
			$smarty->assign([
				'vps_id' => $serviceClass->getId(),
				'vps_vzid' => is_numeric($serviceClass->getVzid()) ? (in_array($event['type'], [get_service_define('KVM_WINDOWS'), get_service_define('CLOUD_KVM_WINDOWS')]) ? 'windows'.$serviceClass->getVzid() : 'linux'.$serviceClass->getVzid()) : $serviceClass->getVzid(),
				'email' => $GLOBALS['tf']->accounts->cross_reference($serviceClass->getCustid()),
				'domain' => DOMAIN,
				'param1' => $event['param1']
			]);
			echo $smarty->fetch(__DIR__.'/../templates/reinstall_osupdate_hdsize.sh.tpl');
			$event->stopPropagation();
		}
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getQueueEnableCd(GenericEvent $event) {
		if (in_array($event['type'], [get_service_define('KVM_LINUX'), get_service_define('KVM_WINDOWS'), get_service_define('CLOUD_KVM_LINUX'), get_service_define('CLOUD_KVM_WINDOWS')])) {
			myadmin_log(self::$module, 'info', self::$name.' Queue Enable Cd', __LINE__, __FILE__);
			$serviceClass = $event->getSubject();
			$smarty = new \TFSmarty();
			$smarty->assign([
				'vps_id' => $serviceClass->getId(),
				'vps_vzid' => is_numeric($serviceClass->getVzid()) ? (in_array($event['type'], [get_service_define('KVM_WINDOWS'), get_service_define('CLOUD_KVM_WINDOWS')]) ? 'windows'.$serviceClass->getVzid() : 'linux'.$serviceClass->getVzid()) : $serviceClass->getVzid(),
				'email' => $GLOBALS['tf']->accounts->cross_reference($serviceClass->getCustid()),
				'domain' => DOMAIN,
				'param1' => $event['param1']
			]);
			echo $smarty->fetch(__DIR__.'/../templates/enable_cd.sh.tpl');
			$event->stopPropagation();
		}
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getQueueDisableCd(GenericEvent $event) {
		if (in_array($event['type'], [get_service_define('KVM_LINUX'), get_service_define('KVM_WINDOWS'), get_service_define('CLOUD_KVM_LINUX'), get_service_define('CLOUD_KVM_WINDOWS')])) {
			myadmin_log(self::$module, 'info', self::$name.' Queue Disable Cd', __LINE__, __FILE__);
			$serviceClass = $event->getSubject();
			$smarty = new \TFSmarty();
			$smarty->assign([
				'vps_id' => $serviceClass->getId(),
				'vps_vzid' => is_numeric($serviceClass->getVzid()) ? (in_array($event['type'], [get_service_define('KVM_WINDOWS'), get_service_define('CLOUD_KVM_WINDOWS')]) ? 'windows'.$serviceClass->getVzid() : 'linux'.$serviceClass->getVzid()) : $serviceClass->getVzid(),
				'email' => $GLOBALS['tf']->accounts->cross_reference($serviceClass->getCustid()),
				'domain' => DOMAIN,
				'param1' => $event['param1']
			]);
			echo $smarty->fetch(__DIR__.'/../templates/disable_cd.sh.tpl');
			$event->stopPropagation();
		}
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getQueueInsertCd(GenericEvent $event) {
		if (in_array($event['type'], [get_service_define('KVM_LINUX'), get_service_define('KVM_WINDOWS'), get_service_define('CLOUD_KVM_LINUX'), get_service_define('CLOUD_KVM_WINDOWS')])) {
			myadmin_log(self::$module, 'info', self::$name.' Queue Insert Cd', __LINE__, __FILE__);
			$serviceClass = $event->getSubject();
			$smarty = new \TFSmarty();
			$smarty->assign([
				'vps_id' => $serviceClass->getId(),
				'vps_vzid' => is_numeric($serviceClass->getVzid()) ? (in_array($event['type'], [get_service_define('KVM_WINDOWS'), get_service_define('CLOUD_KVM_WINDOWS')]) ? 'windows'.$serviceClass->getVzid() : 'linux'.$serviceClass->getVzid()) : $serviceClass->getVzid(),
				'email' => $GLOBALS['tf']->accounts->cross_reference($serviceClass->getCustid()),
				'domain' => DOMAIN,
				'param1' => $event['param1']
			]);
			echo $smarty->fetch(__DIR__.'/../templates/insert_cd.sh.tpl');
			$event->stopPropagation();
		}
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getQueueEjectCd(GenericEvent $event) {
		if (in_array($event['type'], [get_service_define('KVM_LINUX'), get_service_define('KVM_WINDOWS'), get_service_define('CLOUD_KVM_LINUX'), get_service_define('CLOUD_KVM_WINDOWS')])) {
			myadmin_log(self::$module, 'info', self::$name.' Queue Eject Cd', __LINE__, __FILE__);
			$serviceClass = $event->getSubject();
			$smarty = new \TFSmarty();
			$smarty->assign([
				'vps_id' => $serviceClass->getId(),
				'vps_vzid' => is_numeric($serviceClass->getVzid()) ? (in_array($event['type'], [get_service_define('KVM_WINDOWS'), get_service_define('CLOUD_KVM_WINDOWS')]) ? 'windows'.$serviceClass->getVzid() : 'linux'.$serviceClass->getVzid()) : $serviceClass->getVzid(),
				'email' => $GLOBALS['tf']->accounts->cross_reference($serviceClass->getCustid()),
				'domain' => DOMAIN,
				'param1' => $event['param1']
			]);
			echo $smarty->fetch(__DIR__.'/../templates/eject_cd.sh.tpl');
			$event->stopPropagation();
		}
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getQueueStart(GenericEvent $event) {
		if (in_array($event['type'], [get_service_define('KVM_LINUX'), get_service_define('KVM_WINDOWS'), get_service_define('CLOUD_KVM_LINUX'), get_service_define('CLOUD_KVM_WINDOWS')])) {
			myadmin_log(self::$module, 'info', self::$name.' Queue Start', __LINE__, __FILE__);
			$serviceClass = $event->getSubject();
			$smarty = new \TFSmarty();
			$smarty->assign([
				'vps_id' => $serviceClass->getId(),
				'vps_vzid' => is_numeric($serviceClass->getVzid()) ? (in_array($event['type'], [get_service_define('KVM_WINDOWS'), get_service_define('CLOUD_KVM_WINDOWS')]) ? 'windows'.$serviceClass->getVzid() : 'linux'.$serviceClass->getVzid()) : $serviceClass->getVzid(),
				'email' => $GLOBALS['tf']->accounts->cross_reference($serviceClass->getCustid()),
				'domain' => DOMAIN,
				'param1' => $event['param1']
			]);
			echo $smarty->fetch(__DIR__.'/../templates/start.sh.tpl');
			$event->stopPropagation();
		}
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getQueueStop(GenericEvent $event) {
		if (in_array($event['type'], [get_service_define('KVM_LINUX'), get_service_define('KVM_WINDOWS'), get_service_define('CLOUD_KVM_LINUX'), get_service_define('CLOUD_KVM_WINDOWS')])) {
			myadmin_log(self::$module, 'info', self::$name.' Queue Stop', __LINE__, __FILE__);
			$serviceClass = $event->getSubject();
			$smarty = new \TFSmarty();
			$smarty->assign([
				'vps_id' => $serviceClass->getId(),
				'vps_vzid' => is_numeric($serviceClass->getVzid()) ? (in_array($event['type'], [get_service_define('KVM_WINDOWS'), get_service_define('CLOUD_KVM_WINDOWS')]) ? 'windows'.$serviceClass->getVzid() : 'linux'.$serviceClass->getVzid()) : $serviceClass->getVzid(),
				'email' => $GLOBALS['tf']->accounts->cross_reference($serviceClass->getCustid()),
				'domain' => DOMAIN,
				'param1' => $event['param1']
			]);
			echo $smarty->fetch(__DIR__.'/../templates/stop.sh.tpl');
			$event->stopPropagation();
		}
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getQueueRestart(GenericEvent $event) {
		if (in_array($event['type'], [get_service_define('KVM_LINUX'), get_service_define('KVM_WINDOWS'), get_service_define('CLOUD_KVM_LINUX'), get_service_define('CLOUD_KVM_WINDOWS')])) {
			myadmin_log(self::$module, 'info', self::$name.' Queue Restart', __LINE__, __FILE__);
			$serviceClass = $event->getSubject();
			$smarty = new \TFSmarty();
			$smarty->assign([
				'vps_id' => $serviceClass->getId(),
				'vps_vzid' => is_numeric($serviceClass->getVzid()) ? (in_array($event['type'], [get_service_define('KVM_WINDOWS'), get_service_define('CLOUD_KVM_WINDOWS')]) ? 'windows'.$serviceClass->getVzid() : 'linux'.$serviceClass->getVzid()) : $serviceClass->getVzid(),
				'email' => $GLOBALS['tf']->accounts->cross_reference($serviceClass->getCustid()),
				'domain' => DOMAIN,
				'param1' => $event['param1']
			]);
			echo $smarty->fetch(__DIR__.'/../templates/restart.sh.tpl');
			$event->stopPropagation();
		}
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getQueueSetupVnc(GenericEvent $event) {
		if (in_array($event['type'], [get_service_define('KVM_LINUX'), get_service_define('KVM_WINDOWS'), get_service_define('CLOUD_KVM_LINUX'), get_service_define('CLOUD_KVM_WINDOWS')])) {
			myadmin_log(self::$module, 'info', self::$name.' Queue Setup Vnc', __LINE__, __FILE__);
			$serviceClass = $event->getSubject();
			$smarty = new \TFSmarty();
			$smarty->assign([
				'vps_id' => $serviceClass->getId(),
				'vps_vzid' => is_numeric($serviceClass->getVzid()) ? (in_array($event['type'], [get_service_define('KVM_WINDOWS'), get_service_define('CLOUD_KVM_WINDOWS')]) ? 'windows'.$serviceClass->getVzid() : 'linux'.$serviceClass->getVzid()) : $serviceClass->getVzid(),
				'email' => $GLOBALS['tf']->accounts->cross_reference($serviceClass->getCustid()),
				'domain' => DOMAIN,
				'param1' => $event['param1']
			]);
			echo $smarty->fetch(__DIR__.'/../templates/setup_vnc.sh.tpl');
			$event->stopPropagation();
		}
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getQueueResetPassword(GenericEvent $event) {
		if (in_array($event['type'], [get_service_define('KVM_LINUX'), get_service_define('KVM_WINDOWS'), get_service_define('CLOUD_KVM_LINUX'), get_service_define('CLOUD_KVM_WINDOWS')])) {
			myadmin_log(self::$module, 'info', self::$name.' Queue Reset Password', __LINE__, __FILE__);
			$serviceClass = $event->getSubject();
			$smarty = new \TFSmarty();
			$smarty->assign([
				'vps_id' => $serviceClass->getId(),
				'vps_vzid' => is_numeric($serviceClass->getVzid()) ? (in_array($event['type'], [get_service_define('KVM_WINDOWS'), get_service_define('CLOUD_KVM_WINDOWS')]) ? 'windows'.$serviceClass->getVzid() : 'linux'.$serviceClass->getVzid()) : $serviceClass->getVzid(),
				'email' => $GLOBALS['tf']->accounts->cross_reference($serviceClass->getCustid()),
				'domain' => DOMAIN,
				'param1' => $event['param1']
			]);
			echo $smarty->fetch(__DIR__.'/../templates/reset_password.sh.tpl');
			$event->stopPropagation();
		}
	}

}
