<?php

$stdIncludePath = ini_get('include_path');
$extIncludePath = $_SERVER['DOCUMENT_ROOT'].DIRECTORY_SEPARATOR.'PEAR';
$extIncludePath .= PATH_SEPARATOR.$extIncludePath.DIRECTORY_SEPARATOR.'ezc';

ini_set('include_path', $stdIncludePath.PATH_SEPARATOR.$extIncludePath);
require_once "Base/base.php"; // dependent on installation method, see below

ezcBase::addClassRepository(
	dirname(__FILE__),
	dirname(__FILE__).'/autoload',
	'nw'
);

spl_autoload_register('ezcBase::autoload');
?>