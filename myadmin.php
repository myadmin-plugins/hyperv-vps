<?php
/* TODO:
 - service type, category, and services  adding
 - dealing with the SERVICE_TYPES_hyperv define
 - add way to call/hook into install/uninstall
*/
return [
	'name' => 'Hyperv Vps',
	'description' => 'Allows selling of Hyperv Server and VPS License Types.  More info at https://www.netenberg.com/hyperv.php',
	'help' => 'It provides more than one million end users the ability to quickly install dozens of the leading open source content management systems into their web space.  	Must have a pre-existing cPanel license with cPanelDirect to purchase a hyperv license. Allow 10 minutes for activation.',
	'module' => 'vps',
	'author' => 'detain@interserver.net',
	'home' => 'https://github.com/detain/myadmin-hyperv-vps',
	'repo' => 'https://github.com/detain/myadmin-hyperv-vps',
	'version' => '1.0.0',
	'type' => 'service',
	'hooks' => [
		'vps.settings' => ['Detain\MyAdminHyperv\Plugin', 'Settings'],
		/*'function.requirements' => ['Detain\MyAdminHyperv\Plugin', 'Requirements'],
		'vps.activate' => ['Detain\MyAdminHyperv\Plugin', 'Activate'],
		'vps.change_ip' => ['Detain\MyAdminHyperv\Plugin', 'ChangeIp'],
		'ui.menu' => ['Detain\MyAdminHyperv\Plugin', 'Menu'] */
	],
];
