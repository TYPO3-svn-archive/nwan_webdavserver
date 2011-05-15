<?php

########################################################################
# Extension Manager/Repository config file for ext "nwan_webdavserver".
#
# Auto generated 04-05-2011 23:26
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
	'dependencies' => '',
	'conflicts' => '',
	'priority' => '',
	'module' => '',
	'state' => 'alpha',
	'internal' => '',
	'uploadfolder' => 1,
	'createDirs' => '',
	'modify_tables' => '',
	'clearCacheOnLoad' => 0,
	'lockType' => '',
	'author_company' => '',
	'version' => '0.0.0',
	'constraints' => array(
		'depends' => array(
//			'meta_ftpd' => '',
		),
		'conflicts' => array(
		),
		'suggests' => array(
		),
	),
	'_md5_values_when_last_written' => 'a:22:{s:9:"ChangeLog";s:4:"a9ec";s:10:"README.txt";s:4:"ee2d";s:12:"autoload.php";s:4:"24df";s:21:"ext_conf_template.txt";s:4:"d6ff";s:12:"ext_icon.gif";s:4:"1bdc";s:17:"ext_localconf.php";s:4:"af84";s:14:"ext_tables.php";s:4:"9579";s:14:"ext_tables.sql";s:4:"f6f7";s:10:"index.html";s:4:"d41d";s:9:"index.php";s:4:"5c8f";s:16:"locallang_db.php";s:4:"83eb";s:28:"autoload/webdav_autoload.php";s:4:"96c8";s:34:"classes/class.tx_metaftpd_t3io.php";s:4:"418c";s:24:"classes/nwWebdavAuth.php";s:4:"d8ff";s:36:"classes/nwWebdavBackend.20110420.php";s:4:"2333";s:27:"classes/nwWebdavBackend.php";s:4:"43db";s:26:"classes/nwWebdavServer.php";s:4:"cae1";s:24:"classes/typo3Adapter.php";s:4:"54dc";s:17:"config/config.php";s:4:"85d1";s:19:"doc/wizard_form.dat";s:4:"283a";s:20:"doc/wizard_form.html";s:4:"aec7";s:18:"storage/tokens.php";s:4:"6ba2";}',
	'suggests' => array(
	),
);

?>