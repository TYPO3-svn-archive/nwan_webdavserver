<?php

/** Initialize vars from extension conf */
$_EXTCONF = unserialize($_EXTCONF);    // unserializing the configuration so we can use it here:
$initVars = array('realm','natural_file_names');
foreach($initVars as $var) {
  $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$_EXTKEY][$var] = $_EXTCONF[$var] ? trim($_EXTCONF[$var]) : "";
}

?>