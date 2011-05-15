<?php
if (!defined ("TYPO3_MODE")) 	die ("Access denied.");

$tempColumns = Array (
	"tx_nwanwebdavserver_file" => Array (		
		"exclude" => 1,		
		"label" => "LLL:EXT:nwan_webdavserver/locallang_db.php:tt_content.tx_nwanwebdavserver_file",		
		"config" => Array (
			"type" => "group",
			"internal_type" => "file",
			"allowed" => $GLOBALS["TYPO3_CONF_VARS"]["GFX"]["imagefile_ext"],	
			"max_size" => 1000,	
			"uploadfolder" => "uploads/tx_nwanwebdavserver",
			"size" => 1,	
			"minitems" => 0,
			"maxitems" => 1,
		)
	),
);


t3lib_div::loadTCA("tt_content");
t3lib_extMgm::addTCAcolumns("tt_content",$tempColumns,1);
t3lib_extMgm::addToAllTCAtypes("tt_content","tx_nwanwebdavserver_file;;;;1-1-1");					

?>