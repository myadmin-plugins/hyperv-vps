<?php

namespace Detain\MyAdminHyperv;

require_once __DIR__.'/../../../workerman/statistics/Applications/Statistics/Clients/StatisticClient.php';

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
            myadmin_log(self::$module, 'info', 'Hyperv Activation', __LINE__, __FILE__, self::$module, $serviceClass->getId());
            $event->stopPropagation();
        }
    }

    /**
     * @param \Symfony\Component\EventDispatcher\GenericEvent $event
     */
    public static function getDeactivate(GenericEvent $event)
    {
        if ($event['type'] == get_service_define('HYPERV')) {
            $serviceClass = $event->getSubject();
            myadmin_log(self::$module, 'info', self::$name.' Deactivation', __LINE__, __FILE__, self::$module, $serviceClass->getId());
            $GLOBALS['tf']->history->add(self::$module.'queue', $serviceClass->getId(), 'delete', '', $serviceClass->getCustid());
        }
    }

    /**
     * @param \Symfony\Component\EventDispatcher\GenericEvent $event
     */
    public static function getSettings(GenericEvent $event)
    {
        /**
         * @var \MyAdmin\Settings $settings
         **/
        $settings = $event->getSubject();
        $settings->setTarget('module');
        $settings->add_text_setting(self::$module, _('Credentials'), 'vps_hyperv_password', _('HyperV Administrator Password'), _('Administrative password to login to the HyperV server'), $settings->get_setting('VPS_HYPERV_PASSWORD'));
        $settings->add_text_setting(self::$module, _('Slice Costs'), 'vps_slice_hyperv_cost', _('HyperV VPS Cost Per Slice'), _('HyperV VPS will cost this much for 1 slice.'), $settings->get_setting('VPS_SLICE_HYPERV_COST'));
        $settings->add_text_setting(self::$module, _('Slice HyperV Amounts'), 'vps_slice_hyperv_io_min_base', 'IOPS Min Base Value', _('The Base value before factoring in slice counts.'), $settings->get_setting('VPS_SLICE_HYPERV_IO_MIN_BASE'));
        $settings->add_text_setting(self::$module, _('Slice HyperV Amounts'), 'vps_slice_hyperv_io_min_mult', 'IOPS Min Slice Multiplier', _('This value multiplied by the number of slices added to the Base Value will give the Minimum IO value.'), $settings->get_setting('VPS_SLICE_HYPERV_IO_MIN_MULT'));
        $settings->add_text_setting(self::$module, _('Slice HyperV Amounts'), 'vps_slice_hyperv_io_max_base', 'IOPS Max Base Value', _('The Base value before factoring in slice counts.'), $settings->get_setting('VPS_SLICE_HYPERV_IO_MAX_BASE'));
        $settings->add_text_setting(self::$module, _('Slice HyperV Amounts'), 'vps_slice_hyperv_io_max_mult', 'IOPS Max Slice Multiplier', _('This value multiplied by the number of slices added to the Base Value will give the Maximum IO value.'), $settings->get_setting('VPS_SLICE_HYPERV_IO_MAX_MULT'));
        $settings->add_select_master(_(self::$module), _('Default Servers'), self::$module, 'new_vps_hyperv_server', _('HyperV NJ Server'), NEW_VPS_HYPERV_SERVER, 11, 1);
        $settings->add_dropdown_setting(self::$module, _('Out of Stock'), 'outofstock_hyperv', _('Out Of Stock HyperV Secaucus'), _('Enable/Disable Sales Of This Type'), $settings->get_setting('OUTOFSTOCK_HYPERV'), ['0', '1'], ['No', 'Yes']);
        $settings->add_dropdown_setting(self::$module, _('Out of Stock'), 'outofstock_hyperv_la', _('Out Of Stock HyperV LA'), _('Enable/Disable Sales Of This Type'), $settings->get_setting('OUTOFSTOCK_HYPERV_LA'), ['0', '1'], ['No', 'Yes']);
        $settings->add_dropdown_setting(self::$module, _('Out of Stock'), 'outofstock_hyperv_ny', _('Out Of Stock HyperV NY'), _('Enable/Disable Sales Of This Type'), $settings->get_setting('OUTOFSTOCK_HYPERV_NY'), ['0', '1'], ['No', 'Yes']);
        $settings->setTarget('global');
    }

    /**
     * @param \Symfony\Component\EventDispatcher\GenericEvent $event
     */
    public static function getQueue(GenericEvent $event)
    {
        if (in_array($event['type'], [get_service_define('HYPERV')])) {
            $serviceInfo = $event->getSubject();
            $settings = get_module_settings(self::$module);
            myadmin_log(self::$module, 'info', self::$name.' Queue '.ucwords(str_replace('_', ' ', $serviceInfo['action'])).' for VPS '.$serviceInfo['vps_hostname'].'(#'.$serviceInfo['vps_id'].'/'.$serviceInfo['vps_vzid'].')', __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
            $queue_calls = self::getQueueCalls();
            $method = 'queue'.str_replace('_', '', ucwords($serviceInfo['action'], '_'));
            if (method_exists(__CLASS__, $method)) {
                myadmin_log(self::$module, 'info', $serviceInfo['server_info']['vps_name'].' '.$method.' '.$serviceInfo['vps_hostname'].'(#'.$serviceInfo['vps_id'].'/'.$serviceInfo['vps_vzid'].')', __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
                call_user_func([__CLASS__, $method], $serviceInfo);
            } elseif (!isset($queue_calls[$serviceInfo['action']])) {
                myadmin_log(self::$module, 'error', 'Call '.$serviceInfo['action'].' for VPS '.$serviceInfo['vps_hostname'].'(#'.$serviceInfo['vps_id'].'/'.$serviceInfo['vps_vzid'].') Does not Exist for '.self::$name, __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
            } else {
                $calls = $queue_calls[$serviceInfo['action']];
                foreach ($calls as $call) {
                    myadmin_log(self::$module, 'info', $serviceInfo['server_info']['vps_name'].' '.$call.' '.$serviceInfo['vps_hostname'].'(#'.$serviceInfo['vps_id'].'/'.$serviceInfo['vps_vzid'].')', __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
                    \StatisticClient::tick('Hyper-V', $call);
                    try {
                        $soap = new \SoapClient(self::getSoapClientUrl($serviceInfo['server_info']['vps_ip']), self::getSoapClientParams());
                        $response = $soap->$call(self::getSoapCallParams($call, $serviceInfo));
                        \StatisticClient::report('Hyper-V', $call, true, 0, '', STATISTICS_SERVER);
                    } catch (\SoapFault $e) {
                        $msg = $serviceInfo['server_info']['vps_name'].' '.$call.' '.$serviceInfo['vps_hostname'].'(#'.$serviceInfo['vps_id'].'/'.$serviceInfo['vps_vzid'].') Caught exception: '.$e->getMessage();
                        echo $msg.PHP_EOL;
                        myadmin_log(self::$module, 'error', $msg, __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
                        //$event['success'] = FALSE;
                        \StatisticClient::report('Hyper-V', $call, false, $e->getCode(), $e->getMessage(), STATISTICS_SERVER);
                    } catch (\Exception $e) {
                        $msg = $serviceInfo['server_info']['vps_name'].' '.$call.' '.$serviceInfo['vps_hostname'].'(#'.$serviceInfo['vps_id'].'/'.$serviceInfo['vps_vzid'].') Caught exception: '.$e->getMessage();
                        echo $msg.PHP_EOL;
                        myadmin_log(self::$module, 'error', $msg, __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
                        //$event['success'] = FALSE;
                        \StatisticClient::report('Hyper-V', $call, false, $e->getCode(), $e->getMessage(), STATISTICS_SERVER);
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

    public static function getSoapCallParams($call, $serviceInfo)
    {
        if ($call == 'CleanUpResources') {
            return [];
        } elseif ($call == 'ResizeVMHardDrive') {
            return [
                'vmId' => $serviceInfo['vps_vzid'],
                'updatedDriveSizeInGigabytes' => 1 * ((VPS_SLICE_HD * $serviceInfo['vps_slices']) + $serviceInfo['settings']['additional_hd']),
                'hyperVAdminUsername' => 'Administrator',
                'hyperVAdminPassword' => $serviceInfo['server_info']['vps_root'],
            ];
        } elseif ($call == 'CreateVM') {
            return [
                'vmName' => $serviceInfo['vps_hostname'],
                'vhdSize' => 1 * ((VPS_SLICE_HD * $serviceInfo['vps_slices']) + $serviceInfo['settings']['additional_hd']),
                'ramSize' => 1 * VPS_SLICE_RAM * $serviceInfo['vps_slices'],
                'osToInstall' => $serviceInfo['vps_os'],
                'hyperVAdmin' => 'Administrator',
                'adminPassword' => $serviceInfo['server_info']['vps_root']
            ];
        } elseif ($call == 'UpdateVM') {
            return [
                'vmId' => $serviceInfo['vps_vzid'],
                'cpuCores' => ceil((($serviceInfo['vps_slices'] - 2) / 2) + 1),
                'ramMB' => 1 * VPS_SLICE_RAM * $serviceInfo['vps_slices'],
                'bootFromCD' => false,
                'numLockEnabled' => true,
                'hyperVAdmin' => 'Administrator',
                'adminPassword' => $serviceInfo['server_info']['vps_root']
            ];
        } elseif ($call == 'SetVMIOPS') {
            return [
                'vmId' => $serviceInfo['vps_vzid'],
                'minimumOps' => VPS_SLICE_HYPERV_IO_MIN_BASE + (VPS_SLICE_HYPERV_IO_MIN_MULT * $serviceInfo['vps_slices']),
                'maximumOps' => VPS_SLICE_HYPERV_IO_MAX_BASE + (VPS_SLICE_HYPERV_IO_MAX_MULT * $serviceInfo['vps_slices']),
                'adminUsername' => 'Administrator',
                'adminPassword' => $serviceInfo['server_info']['vps_root']
            ];
        } elseif ($call == 'SetVMAdminPassword') {
            return [
                'adminUser' => 'Administrator',
                'adminPassword' => $serviceInfo['server_info']['vps_root'],
                'vmId' => $serviceInfo['vps_vzid'],
                'username' => 'Administrator',
                'existingPassword' => VPS_HYPERV_PASSWORD,
                'newPassword' => vps_get_password($serviceInfo['vps_id'], $serviceInfo['vps_custid'])
            ];
        } elseif ($call == 'AddPublicIp') {
            $db = get_module_db('default');
            $db->query("select vlans_networks from vlans where vlans_id=(select ips_vlan from ips where ips_ip='{$serviceInfo['vps_ip']}')");
            $db->next_record(MYSQL_ASSOC);
            function_requirements('ipcalc');
            $ipinfo = ipcalc(str_replace(':', '', $db->Record['vlans_networks']));
            $ip_parameters = [
                'vmId' => $serviceInfo['vps_vzid'],
                'ip' => $serviceInfo['vps_ip'],
                'defaultGateways' => $ipinfo['hostmin'],
                'subnets' => $ipinfo['netmask'],
                'dns' => ['8.8.8.8', '8.8.4.4'],
                'hyperVAdmin' => 'Administrator',
                'adminPassword' => $serviceInfo['server_info']['vps_root']
            ];
        } elseif (in_array($call, ['GetVMList'])) {
            return [
                'hyperVAdmin' => 'Administrator',
                'adminPassword' => $serviceInfo['server_info']['vps_root']
            ];
        } else {
            return [
                'vmId' => $serviceInfo['vps_vzid'],
                'hyperVAdmin' => 'Administrator',
                'adminPassword' => $serviceInfo['server_info']['vps_root']
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
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
        ]]);
        return [
            'encoding' => 'UTF-8',
            'verifypeer' => false,
            'verifyhost' => false,
            'soap_version' => SOAP_1_2,
            'trace' => 1,
            'exceptions' => 1,
            'connection_timeout' => 600,
            'stream_context' => $context,
            'context' => $context,
        ];
    }

    public static function queueCreate($serviceInfo)
    {
        $db = get_module_db(self::$module);
        $db2 = get_module_db(self::$module);
        $settings = get_module_settings(self::$module);
        myadmin_log('hyperv', 'info', "HyperV Got Here template {$serviceInfo['vps_os']}", __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
        $vzname = $serviceInfo['vps_hostname'];
        myadmin_log('hyperv', 'info', "HyperV Name {$vzname}  VZID {$serviceInfo['vzid']} ID {$serviceInfo['id']}", __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
        $memory = $serviceInfo['settings']['slice_ram'] * $serviceInfo['vps_slices'];
        $diskspace = ($serviceInfo['settings']['slice_hd'] * $serviceInfo['vps_slices']) + $serviceInfo['settings']['additional_hd'];
        $progress = 0;
        $params = \Detain\MyAdminHyperv\Plugin::getSoapClientParams();
        $url = "https://{$serviceInfo['server_info']['vps_ip']}/HyperVService/HyperVService.asmx?WSDL";
        $db->query("update vps set vps_server_status='{$progress}' where vps_id={$serviceInfo['id']}", __LINE__, __FILE__);
        $db->query("select * from vps_templates where template_type=11 and template_available=1 and template_file='{$serviceInfo['vps_os']}'");
        if ($db->num_rows() == 0) {
            $db->query("select * from vps_templates where template_type=11 and template_available=1 limit 1");
            $db->next_record(MYSQL_ASSOC);
            $serviceInfo['vps_os'] = $db->Record['template_file'];
        }
        if (is_uuid($serviceInfo['vps_vzid'])) {
            myadmin_log('hyperv', 'info', 'Existing UUID value found, attempting to delete', __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
            $parameters = [
                'vmId' => $serviceInfo['vps_vzid'],
                'hyperVAdmin' => 'Administrator',
                'adminPassword' => $serviceInfo['server_info']['vps_root']
            ];
            \StatisticClient::tick('Hyper-V', 'TurnOff');
            try {
                $soap = new \SoapClient($url, $params);
                $response = $soap->TurnOff($parameters);
                //\StatisticClient::report('Hyper-V', 'TurnOff', true, 0, '', STATISTICS_SERVER);
                if (isset($response->TurnOffResult->Status)) {
                    $status = $response->TurnOffResult->Status;
                } else {
                    $status = $response->TurnOffResult->Success;
                }
                myadmin_log('hyperv', 'info', "Response Status: {$status}", __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
            } catch (\Exception $e) {
                myadmin_log('hyperv', 'warning', 'TurnOff Caught exception: '.$e->getMessage(), __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
                //\StatisticClient::report('Hyper-V', 'TurnOff', false, $e->getCode(), $e->getMessage(), STATISTICS_SERVER);
            }
            \StatisticClient::report('Hyper-V', 'TurnOff', true, 0, '', STATISTICS_SERVER);
            $progress++;
            $db->query("update vps set vps_server_status='{$progress}' where vps_id={$serviceInfo['id']}", __LINE__, __FILE__);
            \StatisticClient::tick('Hyper-V', 'DeleteVM');
            try {
                $soap = new \SoapClient($url, $params);
                $response = $soap->DeleteVM($parameters);
                //\StatisticClient::report('Hyper-V', 'DeleteVM', true, 0, '', STATISTICS_SERVER);
                if (isset($response->DeleteVMResult->Status)) {
                    $status = $response->DeleteVMResult->Status;
                } else {
                    $status = $response->DeleteVMResult->Success;
                }
                myadmin_log('hyperv', 'info', "Response Status: {$status}", __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
            } catch (\Exception $e) {
                myadmin_log('hyperv', 'warning', 'DeleteVM Caught exception: '.$e->getMessage(), __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
                //\StatisticClient::report('Hyper-V', 'DeleteVM', false, $e->getCode(), $e->getMessage(), STATISTICS_SERVER);
            }
            \StatisticClient::report('Hyper-V', 'DeleteVM', true, 0, '', STATISTICS_SERVER);
            $progress++;
            $db->query("update vps set vps_server_status='{$progress}' where vps_id={$serviceInfo['id']}", __LINE__, __FILE__);
        }
        $start_time = time();
        $create_parameters = [
            'vmName' => $vzname,
            'vhdSize' => $diskspace,
            'ramSize' => $memory,
            'osToInstall' => $serviceInfo['vps_os'],
            'hyperVAdmin' => 'Administrator',
            'adminPassword' => $serviceInfo['server_info']['vps_root']
        ];
        myadmin_log('hyperv', 'info', "CreateVM({$vzname}, {$diskspace}, {$memory}, {$serviceInfo['vps_os']})", __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
        $serviceInfo['vzid'] = '';
        \StatisticClient::tick('Hyper-V', 'CleanUpResources');
        try {
            $soap = new \SoapClient($url, $params);
            $response = $soap->CleanUpResources();
            \StatisticClient::report('Hyper-V', 'CleanUpResources', true, 0, '', STATISTICS_SERVER);
        } catch (\Exception $e) {
            myadmin_log('hyperv', 'info', 'CleanUpResources Caught exception: '.$e->getMessage(), __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
            \StatisticClient::report('Hyper-V', 'CleanUpResources', false, $e->getCode(), $e->getMessage(), STATISTICS_SERVER);
        }
        \StatisticClient::tick('Hyper-V', 'CreateVM');
        try {
            $soap = new \SoapClient($url, $params);
            $response = $soap->CreateVM($create_parameters);
            \StatisticClient::report('Hyper-V', 'CreateVM', true, 0, '', STATISTICS_SERVER);
        } catch (\Exception $e) {
            myadmin_log('hyperv', 'info', 'CreateVM( '.json_encode($create_parameters).' ) Caught exception: '.$e->getMessage(), __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
            \StatisticClient::report('Hyper-V', 'CreateVM', false, $e->getCode(), $e->getMessage(), STATISTICS_SERVER);
            return false;
        }
        myadmin_log('hyperv', 'info', json_encode($response->CreateVMResult), __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
        myadmin_log('hyperv', 'info', $response->CreateVMResult->Status, __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
        if (trim($response->CreateVMResult->Status) == 'Provider load failure') {
            $progress = 25;
            $db->query("update vps set vps_server_status='{$progress}' where vps_id={$serviceInfo['id']}", __LINE__, __FILE__);
            \StatisticClient::tick('Hyper-V', 'CleanUpResources');
            try {
                $soap = new \SoapClient($url, $params);
                $response = $soap->CleanUpResources();
                \StatisticClient::report('Hyper-V', 'CleanUpResources', true, 0, '', STATISTICS_SERVER);
            } catch (\Exception $e) {
                myadmin_log('hyperv', 'info', 'CleanUpResources Caught exception: '.$e->getMessage(), __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
                \StatisticClient::report('Hyper-V', 'CleanUpResources', false, $e->getCode(), $e->getMessage(), STATISTICS_SERVER);
            }
            \StatisticClient::tick('Hyper-V', 'CreateVM');
            try {
                $soap = new \SoapClient($url, $params);
                $response = $soap->CreateVM($create_parameters);
                \StatisticClient::report('Hyper-V', 'CreateVM', true, 0, '', STATISTICS_SERVER);
                myadmin_log('hyperv', 'info', json_encode($response->CreateVMResult), __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
                myadmin_log('hyperv', 'info', $response->CreateVMResult->Status, __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
            } catch (\Exception $e) {
                myadmin_log('hyperv', 'info', 'CreateVM( '.json_encode($create_parameters).' ) Caught exception: '.$e->getMessage(), __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
                \StatisticClient::report('Hyper-V', 'CreateVM', false, $e->getCode(), $e->getMessage(), STATISTICS_SERVER);
            }
        }
        //$response = $soap->CreateVirtualMachine($create_parameters);
        $progress = 50;
        $db->query("update vps set vps_server_status='{$progress}' where vps_id={$serviceInfo['id']}", __LINE__, __FILE__);
        myadmin_log('hyperv', 'info', 'finished updating the db', __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
        $serviceInfo['vzid'] = $response->CreateVMResult->Status;
        //$vps['vzid'] = $response->CreateVirtualMachineResult->Status;
        if (mb_strlen($serviceInfo['vzid']) != 36) {
            myadmin_log('hyperv', 'info', "id should not be '{$serviceInfo['vzid']}' " . mb_strlen($serviceInfo['vzid']).' chars long, doing a match up and looking', __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
            unset($soap);
            \StatisticClient::tick('Hyper-V', 'GetVMList');
            try {
                $soap = new \SoapClient($url, $params);
                $response = $soap->GetVMList([
                    'hyperVAdmin' => 'Administrator',
                    'adminPassword' => $serviceInfo['server_info']['vps_root']
                ]);
                \StatisticClient::report('Hyper-V', 'GetVMList', true, 0, '', STATISTICS_SERVER);
            } catch (\Exception $e) {
                \StatisticClient::report('Hyper-V', 'GetVMList', false, $e->getCode(), $e->getMessage(), STATISTICS_SERVER);
            }
            if (isset($response->GetVMListResult->VMList->VirtualMachineSummary->Name)) {
                $each = $response->GetVMListResult->VMList;
            } else {
                $each = $response->GetVMListResult->VMList->VirtualMachineSummary;
            }
            foreach ($each as $vm) {
                if ($vm->Name == $vzname) {
                    $serviceInfo['vzid'] = $vm->VmId;
                }
            }
        }
        $progress++;
        $db->query("update vps set vps_server_status='{$progress}' where vps_id={$serviceInfo['id']}", __LINE__, __FILE__);
        if (mb_strlen($serviceInfo['vzid']) != 36) {
            myadmin_log('hyperv', 'info', "Invalid ID {$serviceInfo['vzid']} Length " . mb_strlen($serviceInfo['vzid']), __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
            return false;
        }
        myadmin_log('hyperv', 'info', "Created HyperV VPS {$serviceInfo['id']} : {$serviceInfo['vzid']}", __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
        $db->query("update {$settings['TABLE']} set vps_vzid='" . $db->real_escape($serviceInfo['vzid']) . "' where {$settings['PREFIX']}_id='{$serviceInfo['vps_id']}'", __LINE__, __FILE__);
        $extra = $serviceInfo['extra'];
        $extra['id'] = $serviceInfo['vzid'];
        $extra['response'] = $response;
        $update_parameters = [
            'vmId' => $serviceInfo['vzid'],
            'cpuCores' => ceil((($serviceInfo['vps_slices'] - 2) / 2) + 1),
            'ramMB' => 1 * VPS_SLICE_RAM * $serviceInfo['vps_slices'],
            'bootFromCD' => false,
            'numLockEnabled' => true,
            'hyperVAdmin' => 'Administrator',
            'adminPassword' => $serviceInfo['server_info']['vps_root']
        ];
        $iops_parameters = [
            'vmId' => $serviceInfo['vzid'],
            'minimumOps' => VPS_SLICE_HYPERV_IO_MIN_BASE + (VPS_SLICE_HYPERV_IO_MIN_MULT * $serviceInfo['vps_slices']),
            'maximumOps' => VPS_SLICE_HYPERV_IO_MAX_BASE + (VPS_SLICE_HYPERV_IO_MAX_MULT * $serviceInfo['vps_slices']),
            'adminUsername' => 'Administrator',
            'adminPassword' => $serviceInfo['server_info']['vps_root']
        ];
        myadmin_log('hyperv', 'info', "SetVMIOPS({$serviceInfo['vzid']}, " . (VPS_SLICE_HYPERV_IO_MIN_BASE + (VPS_SLICE_HYPERV_IO_MIN_MULT * $serviceInfo['vps_slices'])).','.(VPS_SLICE_HYPERV_IO_MAX_BASE + (VPS_SLICE_HYPERV_IO_MAX_MULT * $serviceInfo['vps_slices'])).')', __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
        \StatisticClient::tick('Hyper-V', 'SetVMIOPS');
        try {
            $soap = new \SoapClient($url, $params);
            $response = $soap->SetVMIOPS($iops_parameters);
            \StatisticClient::report('Hyper-V', 'SetVMIOPS', true, 0, '', STATISTICS_SERVER);
            myadmin_log('hyperv', 'info', 'SetVMIOPS Response: '.json_encode($response->SetVMIOPSResult), __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
        } catch (\Exception $e) {
            myadmin_log('hyperv', 'error', 'SetVMIOPS Caught exception: '.$e->getMessage(), __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
            \StatisticClient::report('Hyper-V', 'SetVMIOPS', false, $e->getCode(), $e->getMessage(), STATISTICS_SERVER);
        }
        function_requirements('get_server_ip_info');
        $dbi = get_module_db('default');
        $dbi->query("select vlans_networks from vlans where vlans_id=(select ips_vlan from ips where ips_ip='{$serviceInfo['ip']}')");
        $dbi->next_record();
        function_requirements('ipcalc');
        $ipinfo = ipcalc(str_replace(':', '', $dbi->Record['vlans_networks']));
        //$ipinfo = get_server_ip_info($vps['ip']);
        $ip_parameters = [
            'vmId' => $serviceInfo['vzid'],
            'ip' => $serviceInfo['ip'],
            'defaultGateways' => $ipinfo['hostmin'],
            'subnets' => $ipinfo['netmask'],
            'dns' => ['8.8.8.8', '8.8.4.4'],
            'hyperVAdmin' => 'Administrator',
            'adminPassword' => $serviceInfo['server_info']['vps_root']
        ];
        /*
        myadmin_log('hyperv', 'info', "AddPublicIp(" . str_replace("\n", "", json_encode($ip_parameters)) . ")", __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
        try {
            $response = $soap->AddPublicIp($ip_parameters);
        } catch (\Exception $e) {
            myadmin_log('hyperv', 'error', 'Caught exception: '.$e->getMessage(), __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
            return false;
        }
        */
        \StatisticClient::tick('Hyper-V', 'UpdateVM');
        try {
            $soap = new \SoapClient($url, $params);
            $response = $soap->UpdateVM($update_parameters);
            \StatisticClient::report('Hyper-V', 'UpdateVM', true, 0, '', STATISTICS_SERVER);
            myadmin_log('hyperv', 'info', 'UpdateVM ".json_encode($update_parameters)." returned '.json_encode($response->UpdateVMResult), __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
            if (isset($response->UpdateVMResult->Status)) {
                $status = $response->UpdateVMResult->Status;
            } else {
                $status = $response->UpdateVMResult->Success;
            }
            myadmin_log('hyperv', 'info', "UpdateVM Response Status: {$status}", __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
        } catch (\Exception $e) {
            myadmin_log('hyperv', 'error', 'UpdateVM Caught exception: '.$e->getMessage(), __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
            \StatisticClient::report('Hyper-V', 'UpdateVM', false, $e->getCode(), $e->getMessage(), STATISTICS_SERVER);
        }
        $parameters = [
            'vmId' => $serviceInfo['vzid'], 'hyperVAdmin' => 'Administrator', 'adminPassword' => $serviceInfo['server_info']['vps_root']
        ];
        //myadmin_log('hyperv', 'info', "TurnON(" . str_replace("\n", "", json_encode($parameters)) . ")", __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
        myadmin_log('hyperv', 'info', "TurnON({$serviceInfo['vzid']})", __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
        \StatisticClient::tick('Hyper-V', 'TurnON');
        try {
            $soap = new \SoapClient($url, $params);
            $turnon_response = $soap->TurnON($parameters);
            \StatisticClient::report('Hyper-V', 'TurnON', true, 0, '', STATISTICS_SERVER);
            myadmin_log('hyperv', 'info', "TurnON {$serviceInfo['id']} : {$serviceInfo['vzid']} Status " . $turnon_response->TurnONResult->Status, __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
        } catch (\Exception $e) {
            myadmin_log('hyperv', 'error', "TurnON {$serviceInfo['id']} : {$serviceInfo['vzid']} Caught exception: " . $e->getMessage(), __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
            \StatisticClient::report('Hyper-V', 'TurnON', false, $e->getCode(), $e->getMessage(), STATISTICS_SERVER);
            return false;
        }
        $progress += 2;
        $db->query("update vps set vps_server_status='{$progress}' where vps_id={$serviceInfo['id']}", __LINE__, __FILE__);
        if (preg_match('/Failed to change virtual system state. Error Code= JobStarted . Current state =.*/', trim($turnon_response->TurnONResult->Status))) {
            \StatisticClient::tick('Hyper-V', 'TurnON');
            try {
                $soap = new \SoapClient($url, $params);
                $turnon_response = $soap->TurnON($parameters);
                \StatisticClient::report('Hyper-V', 'TurnON', true, 0, '', STATISTICS_SERVER);
                myadmin_log('hyperv', 'info', "TurnON {$serviceInfo['id']} : {$serviceInfo['vzid']} Status " . $turnon_response->TurnONResult->Status, __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
            } catch (\Exception $e) {
                myadmin_log('hyperv', 'error', "TurnON {$serviceInfo['id']} : {$serviceInfo['vzid']} Caught exception: " . $e->getMessage(), __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
                \StatisticClient::report('Hyper-V', 'TurnON', false, $e->getCode(), $e->getMessage(), STATISTICS_SERVER);
                return false;
            }
            $progress++;
            $db->query("update vps set vps_server_status='{$progress}' where vps_id={$serviceInfo['id']}", __LINE__, __FILE__);
        }
        if (preg_match('/Failed to change virtual system state. Error Code= JobStarted . Current state =.*/', trim($turnon_response->TurnONResult->Status))) {
            myadmin_log('hyperv', 'info', "Bailing on hyperv create ({$serviceInfo['id']} : {$serviceInfo['vzid']}) - unable to finish", __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
            $progress = 0;
            $db->query("update vps set vps_server_status='{$progress}' where vps_id={$serviceInfo['id']}", __LINE__, __FILE__);
            return false;
        }
        /*
        //myadmin_log('hyperv', 'info', "AddPublicIp(" . str_replace("\n", "", json_encode($ip_parameters)) . ")", __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
        myadmin_log('hyperv', 'info', "AddPublicIp(" . json_encode($ip_parameters) . ")", __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
        try {
            $ip_response = $soap->AddPublicIp($ip_parameters);
        } catch (\Exception $e) {
            myadmin_log('hyperv', 'error', 'Caught exception: '.$e->getMessage(), __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
            return false;
        }
        */
        // loopuntil it finds there is an ip (any) set (booted up)
        $current_ip = false;
        $maxLoop = 100;
        $loop = 0;
        while ($current_ip === false || $current_ip = '' || $loop >= $maxLoop) {
            $loop++;
            sleep(20);
            //myadmin_log('hyperv', 'info', "(" . str_replace("\n", "", json_encode($getvm_parameters)) . ")", __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
            //myadmin_log('hyperv', 'info', "GetVM {$vps['id']} : {$vps['vzid']}", __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
            \StatisticClient::tick('Hyper-V', 'GetVM');
            try {
                unset($soap);
                $soap = new \SoapClient($url, $params);
                $parameters = [
                    'vmId' => $serviceInfo['vzid'],
                    'hyperVAdmin' => 'Administrator',
                    'adminPassword' => $serviceInfo['server_info']['vps_root']
                ];
                unset($getvm_response);
                $getvm_response = $soap->GetVM($parameters);
                \StatisticClient::report('Hyper-V', 'GetVM', true, 0, '', STATISTICS_SERVER);
                myadmin_log('hyperv', 'info', "GetVM {$serviceInfo['id']} : {$serviceInfo['vzid']} got {$getvm_response->GetVMResult->Status}", __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
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
                myadmin_log('hyperv', 'warning', "GetVM {$serviceInfo['id']} : {$serviceInfo['vzid']} Caught exception: " . $e->getMessage(), __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
                \StatisticClient::report('Hyper-V', 'GetVM', false, $e->getCode(), $e->getMessage(), STATISTICS_SERVER);
                //return false;
            }
            $progress = 70;
            $db->query("update vps set vps_server_status='{$progress}' where vps_id={$serviceInfo['id']}", __LINE__, __FILE__);
        }
        if ($current_ip != $serviceInfo['ip']) {
            //myadmin_log('hyperv', 'info', "AddPublicIp(" . str_replace("\n", "", json_encode($ip_parameters)) . ")", __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
            \StatisticClient::tick('Hyper-V', 'AddPublicIp');
            try {
                unset($soap);
                $soap = new \SoapClient($url, $params);
                $ip_response = $soap->AddPublicIp($ip_parameters);
                \StatisticClient::report('Hyper-V', 'AddPublicIp', true, 0, '', STATISTICS_SERVER);
                myadmin_log('hyperv', 'info', "AddPublicIp({$serviceInfo['vzid']}, {$serviceInfo['ip']}) returned " . json_encode($ip_response->AddPublicIpResult), __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
            } catch (\Exception $e) {
                myadmin_log('hyperv', 'error', "AddPublicIp {$serviceInfo['id']} : {$serviceInfo['vzid']} Caught exception: " . $e->getMessage(), __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
                \StatisticClient::report('Hyper-V', 'AddPublicIp', false, $e->getCode(), $e->getMessage(), STATISTICS_SERVER);
                return false;
            }
        }
        $progress = 75;
        $db->query("update vps set vps_server_status='{$progress}' where vps_id={$serviceInfo['id']}", __LINE__, __FILE__);
        $tries = 0;
        $max_tries = 30;
        $finished = false;
        $pass = $serviceInfo['origrootpass'];
        $password_parameters = [
            'adminUser' => 'Administrator',
            'adminPassword' => $serviceInfo['server_info']['vps_root'],
            'vmId' => $serviceInfo['vzid'],
            'username' => 'Administrator',
            'existingPassword' => 'H0wdy',
            'newPassword' => $serviceInfo['origrootpass']
        ];
        while ($finished == false && $tries < $max_tries) {
            $exception = false;
            \StatisticClient::tick('Hyper-V', 'SetVMAdminPassword');
            try {
                unset($soap);
                $soap = new \SoapClient($url, $params);
                $pass_respopassword = $soap->SetVMAdminPassword($password_parameters);
                \StatisticClient::report('Hyper-V', 'SetVMAdminPassword', true, 0, '', STATISTICS_SERVER);
                myadmin_log('hyperv', 'info', "SetVMAdminPassword ({$serviceInfo['vzid']}, {$serviceInfo['origrootpass']}) = " . json_encode($pass_respopassword), __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
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
                \StatisticClient::report('Hyper-V', 'SetVMAdminPassword', false, $e->getCode(), $e->getMessage(), STATISTICS_SERVER);
                $exception = true;
            }
            if ($progress < 99) {
                $progress++;
                $db->query("update vps set vps_server_status='{$progress}' where vps_id={$serviceInfo['id']}", __LINE__, __FILE__);
            }
            $tries++;
            myadmin_log('hyperv', 'warning', "SetVMAdminPassword {$serviceInfo['vzid']} (Attempt {$tries}/{$max_tries}) {$status}", __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
            if ($exception == true || $status == 'Exception has been thrown by the target of an invocation.') {
                $exception = false;
                $pass = generateRandomString(10, 2, 2, 1, 1);
                $password_parameters['newPassword'] = $pass;
                myadmin_log('hyperv', 'warning', "SetVMAdminPassword {$serviceInfo['vzid']} Assuming password not complex enough , setting it to a random password, SetVMAdminPassword new pass {$pass}", __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
                \StatisticClient::tick('Hyper-V', 'SetVMAdminPassword');
                try {
                    unset($soap);
                    $soap = new \SoapClient($url, $params);
                    $pass_respopassword = $soap->SetVMAdminPassword($password_parameters);
                    \StatisticClient::report('Hyper-V', 'SetVMAdminPassword', true, 0, '', STATISTICS_SERVER);
                    $status = trim($pass_respopassword->SetVMAdminPasswordResult->Success);
                    if ($status == 'Exception has been thrown by the target of an invocation.') {
                        $exception = true;
                    }
                } catch (\Exception $e) {
                    $status = trim($e->getMessage());
                    \StatisticClient::report('Hyper-V', 'SetVMAdminPassword', false, $e->getCode(), $e->getMessage(), STATISTICS_SERVER);
                    $exception = true;
                }
                if ($progress < 99) {
                    $progress++;
                    $db->query("update vps set vps_server_status='{$progress}' where vps_id={$serviceInfo['id']}", __LINE__, __FILE__);
                }
                myadmin_log('hyperv', 'warning', "SetVMAdminPassword {$serviceInfo['vzid']} (Attempt {$tries}/{$max_tries}) {$status}", __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
            }
            if ($status == 'Access is denied.') {
                myadmin_log('hyperv', 'info', "SetVMAdminPassword (Attempt {$tries}/{$max_tries}) Invalid Template Password?", __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
            }
            if ($exception == true || ($status == 'The network path was not found.')) {
                //myadmin_log('hyperv', 'info', "SetVMAdminPassword (Attempt {$tries}/{$max_tries}) Sleeping", __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
                sleep(10);
                myadmin_log('hyperv', 'info', "SetVMAdminPassword {$serviceInfo['vzid']} (Attempt {$tries}/{$max_tries}) Finished Sleeping", __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
                continue;
            } elseif (isset($pass_respopassword) && $pass_respopassword->SetVMAdminPasswordResult->Success == 1) {
                myadmin_log('hyperv', 'info', "SetVMAdminPassword {$serviceInfo['vzid']} Successful", __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
                $finished = true;
                if ($pass != $serviceInfo['origrootpass']) {
                    $db->query(
                        make_insert_query('history_log', [
                        'history_id' => null,
                        'history_sid' => '',
                        'history_timestamp' => mysql_now(),
                        'history_creator' => $serviceInfo['vps_custid'],
                        'history_owner' => $serviceInfo['vps_custid'],
                        'history_section' => $settings['PREFIX'],
                        'history_type' => 'password',
                        'history_new_value' => $serviceInfo['vps_id'],
                        'history_old_value' => $pass
                    ]),
                        __LINE__,
                        __FILE__
                    );
                }
                vps_windows_welcome_email($serviceInfo['vps_id'], self::$module);
                continue;
            }
        }
        $password_parameters['username'] = 'int-user';
        \StatisticClient::tick('Hyper-V', 'SetVMAdminPassword');
        try {
            unset($soap);
            $soap = new \SoapClient($url, $params);
            $pass_respopassword = $soap->SetVMAdminPassword($password_parameters);
            \StatisticClient::report('Hyper-V', 'SetVMAdminPassword', true, 0, '', STATISTICS_SERVER);
            myadmin_log('hyperv', 'info', "SetVMAdminPassword ({$serviceInfo['vzid']}, {$serviceInfo['origrootpass']}) = " . json_encode($pass_respopassword), __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
            if (isset($pass_respopassword->SetVMAdminPasswordResult->Status)) {
                $status = trim($pass_respopassword->SetVMAdminPasswordResult->Status);
            } else {
                $status = trim($pass_respopassword->SetVMAdminPasswordResult->Success);
            }
        } catch (\Exception $e) {
            $status = trim($e->getMessage());
            \StatisticClient::report('Hyper-V', 'SetVMAdminPassword', false, $e->getCode(), $e->getMessage(), STATISTICS_SERVER);
        }
        myadmin_log('hyperv', 'info', "Start Time {$start_time}", __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
        $end_time = time();
        myadmin_log('hyperv', 'info', "End Time {$end_time}", __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
        $install_time = $end_time - $start_time;
        myadmin_log('hyperv', 'info', "Install Time {$install_time}", __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
        $progress = 100;
        $q1 = "update vps set vps_server_status='{$progress}' where vps_id={$serviceInfo['id']}";
        $q2 = "update vps_master_details set vps_last_install_time={$install_time} where vps_id={$serviceInfo['server_info']['vps_id']}";
        myadmin_log('hyperv', 'info', $q1, __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
        $db2->query($q1, __LINE__, __FILE__);
        myadmin_log('hyperv', 'info', $q2, __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
        $db2->query($q2, __LINE__, __FILE__);
        $db->query("update {$settings['TABLE']} set vps_os='" . $db->real_escape($serviceInfo['vps_os']) . "', vps_extra='" . $db->real_escape(myadmin_stringify($extra)) . "' where {$settings['PREFIX']}_id='{$serviceInfo[$settings['PREFIX'].'_id']}'", __LINE__, __FILE__);
        return true;
    }
}
