<?php
ob_start();
date_default_timezone_set('Europe/Berlin');
error_reporting (E_ALL ^ E_NOTICE);
set_time_limit(0);

define('DS', DIRECTORY_SEPARATOR);
define('PS', PATH_SEPARATOR);

require_once dirname(__FILE__).DS.'config'.DS.'config.php';
require_once 'autoload.php';

$webdav = nwWebdavServer::getInstance($CFG);
$webdav->serve();

ob_end_flush();