<?php

########################################################################
# Extension Manager/Repository config file for ext "nwan_webdavserver".
#
# Auto generated 13-08-2010 22:03
#
# Manual updates:
# Only the data in the array - everything else is removed by next
# writing. "version" and "dependencies" must not be touched!
########################################################################

$EM_CONF[$_EXTKEY] = array(
	'title' => 'WebDAV-Access to TYPO3-BE',
	'description' => 'Enable webDAV-access to TYPO3 backend.
Rewrite of ext: meta_ftpd using ezComponents
(Extension doesn\'t provide ezComponents itself! Library needs to be available in the include_path)',
	'category' => 'services',
	'author' => 'Andreas Neumann',
	'author_email' => 'netzweberei@googlemail.com',
	'shy' => '',
	'dependencies' => 'meta_ftpd',
	'conflicts' => '',
	'priority' => '',
	'module' => '',
	'state' => 'alpha',
	'internal' => '',
	'uploadfolder' => 0,
	'createDirs' => '',
	'modify_tables' => '',
	'clearCacheOnLoad' => 0,
	'lockType' => '',
	'author_company' => '',
	'version' => '0.0.0',
	'constraints' => array(
		'depends' => array(
			'meta_ftpd' => '',
		),
		'conflicts' => array(
		),
		'suggests' => array(
		),
	),
	'_md5_values_when_last_written' => 'a:12:{s:9:"ChangeLog";s:4:"a9ec";s:10:"README.txt";s:4:"ee2d";s:26:"class.tx_metaftpd_t3io.php";s:4:"3468";s:10:"config.php";s:4:"9ca4";s:15:"custom_auth.php";s:4:"f732";s:12:"ext_icon.gif";s:4:"1bdc";s:10:"index.html";s:4:"3c9f";s:21:"tutorial_autoload.php";s:4:"129b";s:10:"webdav.php";s:4:"3c9f";s:40:"backends/class.t3WebdavHybridBackend.php";s:4:"a29a";s:19:"doc/wizard_form.dat";s:4:"283a";s:20:"doc/wizard_form.html";s:4:"aec7";}',
	'suggests' => array(
	),
);

?>