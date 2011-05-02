<?php
ob_start();
date_default_timezone_set('Europe/Berlin');
error_reporting (E_ALL ^ E_NOTICE);
ini_set('log_errors', true);
ini_set('error_log', '/Users/andreas/Sites/dummy-4.3.3/error.log');
set_time_limit(0);

define('DS', DIRECTORY_SEPARATOR);
define('PS', PATH_SEPARATOR);
define('NWAN_WEBDAVSERVER_ROOT', dirname(__FILE__));

require_once dirname(__FILE__).DS.'config'.DS.'config.php';
require_once 'autoload.php';

$webdav = nwWebdavServer::getInstance($CFG);
$webdav->serve();
ob_end_flush();