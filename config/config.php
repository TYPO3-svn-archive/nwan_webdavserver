<?php
define('TYPO3_OS', stristr(PHP_OS,'win') && !stristr(PHP_OS,'darwin')?'WIN':'');
define('TYPO3_MODE', 'BE');
define('TYPO3_mainDir', 'typo3/');
if (!defined('PATH_site')) 
{
	// Automatic site-dectection,
	// works only, if this EXT is installed locally
	// Define manually otherwise! 
	define('PATH_site', current(explode('typo3conf', dirname(__FILE__))));
}
if (!defined('PATH_t3lib'))
{ 
	define('PATH_t3lib', PATH_site.'t3lib/');
}

/*******************************************************************************
 * make extension indepenendent of "meta_ftpd",
 * preserve some global defined functions from nano-ftpd
 ******************************************************************************/
function db_connect($dbhost, $dbname, $dbuser, $dbpass) {

	global $CFG, $DB_DIE_ON_FAIL, $DB_DEBUG;

	if (! $dbh = @mysql_connect($dbhost, $dbuser, $dbpass)) {
		if ($DB_DEBUG) {
			$CFG->log->write("MySQL: Can't connect to $dbhost as $dbuser\n");
			$CFG->log->write("MySQL: Error: ", mysql_error() . "\n");
		} else {
			$CFG->log->write("MySQL: Database error encountered\n");
		}

		if ($DB_DIE_ON_FAIL) {
			$CFG->log->write("MySQL: This script cannot continue, terminating.\n");
			die();
		}
	}

	$selection = mysql_select_db($dbname, $dbh);

	if (! $selection) {
		if ($DB_DEBUG) {
			echo "Can't select database $dbname";
			echo "MySQL Error: ", mysql_error();
		} else {
			echo "Database error encountered";
		}

		if ($DB_DIE_ON_FAIL) {
			echo "This script cannot continue, terminating.";
			die();
		}
	}

	return $dbh;
}

/*******************************************************************************
 * Mandatory libraries included
 ******************************************************************************/
require_once(PATH_t3lib.'class.t3lib_timetrack.php');
require_once(PATH_t3lib.'class.t3lib_div.php');
require_once(PATH_t3lib.'class.t3lib_extmgm.php');
require_once(PATH_t3lib.'class.t3lib_tcemain.php');
require_once(PATH_t3lib.'class.t3lib_db.php');
if (!defined('PATH_tslib')) 
{
	if (is_dir(PATH_site.'typo3/sysext/cms/tslib/')) 
	{
		define('PATH_tslib', PATH_site.'typo3/sysext/cms/tslib/');
	} 
	elseif (is_dir(PATH_site.'tslib/')) 
	{
		define('PATH_tslib', PATH_site.'tslib/');
	}
}
/*******************************************************************************
 * Checking environment
 ******************************************************************************/
 if (t3lib_div::int_from_ver(phpversion())<4001000) 
 {
 	die ('TYPO3 runs with PHP4.1.0+ only');
 }
 if (isset($_POST['GLOBALS']) || isset($_GET['GLOBALS']))
 {     
 	die('You cannot set the GLOBALS-array from outside the script.');
 }
 if (!get_magic_quotes_gpc())
 {
	t3lib_div::addSlashesOnArray($_GET);
	t3lib_div::addSlashesOnArray($_POST);
	$HTTP_GET_VARS = $_GET;
	$HTTP_POST_VARS = $_POST;
 }
/*******************************************************************************
 * Load default configuration
 ******************************************************************************/
define('PATH_typo3conf', PATH_site.'typo3conf/');
require(PATH_t3lib.'config_default.php');
require_once(PATH_typo3conf.'localconf.php');
/*******************************************************************************
 * Libraries included
 ******************************************************************************/
require_once(PATH_tslib.'class.tslib_fe.php');
require_once(PATH_t3lib.'class.t3lib_page.php');
require_once(PATH_t3lib.'class.t3lib_userauth.php');
require_once(PATH_tslib.'class.tslib_feuserauth.php');
require_once(PATH_t3lib.'class.t3lib_tstemplate.php');
require_once(PATH_t3lib.'class.t3lib_cs.php');
require_once(PATH_t3lib.'class.t3lib_basicfilefunc.php');
require_once(PATH_t3lib.'class.t3lib_userauthgroup.php');
require_once(PATH_t3lib.'class.t3lib_extfilefunc.php');
require_once(PATH_t3lib.'class.t3lib_tcemain.php');
require_once(PATH_t3lib.'class.t3lib_userauthgroup.php');
require_once (PATH_t3lib.'class.t3lib_befunc.php');
require_once (PATH_t3lib.'class.t3lib_userauthgroup.php');
require_once (PATH_t3lib.'class.t3lib_beuserauth.php');
require_once (PATH_t3lib.'class.t3lib_tsfebeuserauth.php');
/*******************************************************************************
 * Initialising database-connector
 ******************************************************************************/
$TYPO3_DB = t3lib_div::makeInstance('t3lib_DB');
$TYPO3_DB->debugOutput = $TYPO3_CONF_VARS['SYS']['sqlDebug'];
/*******************************************************************************
 * Create $TSFE object (TSFE = TypoScript Front End)
 ******************************************************************************/
$TSFE = t3lib_div::makeInstance('tslib_fe',
	$TYPO3_CONF_VARS,
	t3lib_div::_GP('id'),
	t3lib_div::_GP('type'),
	t3lib_div::_GP('no_cache'),
	t3lib_div::_GP('cHash'),
	t3lib_div::_GP('jumpurl'),
	t3lib_div::_GP('MP'),
	t3lib_div::_GP('RDCT')
);
/******************************************************************************/
$TSFE->connectToDB();
/*******************************************************************************
 * Init FrontendUser
 * and start timetracking (
 *  required as a global var eg in 
 *  typo3_src-4.3.3/typo3/sysext/cms/tslib/class.tslib_fe.php#767
 * )
 ******************************************************************************/
$TT = new t3lib_timeTrack;
$TSFE->initFEuser();
/*******************************************************************************
 * Proces the ID, type and other parameters
 * After this point we have an array, $page in TSFE, 
 * which is the page-record of the current page, $id
 ******************************************************************************/
$TSFE->clear_preview();
$TSFE->determineId();
/******************************************************************************* 
 * Now, if there is a backend user logged in and he has NO access to this page, 
 * then re-evaluate the id shown!
 ******************************************************************************/
if (
	$TSFE->beUserLogin 
	&& (
		!$BE_USER->extPageReadAccess($TSFE->page) 
		|| t3lib_div::_GP('ADMCMD_noBeUser')
	)
)
{
	// Remove user
	unset($BE_USER);
	$TSFE->beUserLogin = 0;
	
	// Re-evaluate the page-id.
	$TSFE->checkAlternativeIdMethods();
	$TSFE->clear_preview();
	$TSFE->determineId();
}
$TSFE->makeCacheHash();
$TSFE->includeTCA();
/*******************************************************************************
 * BE_USER
 ******************************************************************************/
$BE_USER='';
$TYPO3_MISC['microtime_BE_USER_start'] = microtime();
$TYPO3_MISC['microtime_BE_USER_end'] = microtime();
/*******************************************************************************
 * The backend language engine is started (ext: "lang")
 ******************************************************************************/
if (!defined('PATH_typo3'))
{ 
	define('PATH_typo3', PATH_site.TYPO3_mainDir);
}
require_once(PATH_typo3.'sysext/lang/lang.php');
$LANG = t3lib_div::makeInstance('language');
$LANG->init($BE_USER->uc['lang']);
/*******************************************************************************
 * Connect to the database
 ******************************************************************************/
$TYPO3_DB = t3lib_div::makeInstance('t3lib_DB');
$TYPO3_PAGE=t3lib_div::makeInstance('t3lib_pageSelect');
$TYPO3_TCE=t3lib_div::makeInstance('t3lib_TCEmain');
$TYPO3_CONF_VARS['BE']['fileExtensions'] = array (
    'webspace' => array('allow'=>'', 'deny'=>'php3,php'),
    'ftpspace' => array('allow'=>'*', 'deny'=>'')

);
$result = $TYPO3_DB->sql_pconnect(TYPO3_db_host, TYPO3_db_username, TYPO3_db_password); 
if (!$result)
{
	die("Couldn't connect to database at ".TYPO3_db_host);
}
$TYPO3_DB->sql_select_db(TYPO3_db); 

$GLOBALS['LANG']->charSet = 'utf-8';

class object{}
$CFG = new object();
$CFG->dbuser 			= TYPO3_db_username;
$CFG->dbpass 			= TYPO3_db_password;
$CFG->dbhost 			= TYPO3_db_host;
$CFG->dbname 			= TYPO3_db;
$CFG->dbtype			= "mysql";
$CFG->debuglevel		= array(-300);
$CFG->debugfunction		= array(
	'T3ListDir', 
//	'T3ReplaceMountPointsByPath', 
//	'isT3', 
//	'T3MakeFilePath', 
//	'T3IsDir', 
//	'T3GetFileMount',
//	'serve',
//	'processURL',
//	'T3FileSize',
//	'createResource',
//	'authorize',
//	'getContentTreeCollection',
//	'getCollectionMembers',
//	'fetchNodeInfo',
//	'T3FileSize',
//	'createCollection',
//	'T3GetPid',
);
$CFG->T3PAGE			= $TYPO3_PAGE;
$CFG->T3DB				= $TYPO3_DB;
$CFG->T3TCE				= $TYPO3_TCE;
$CFG->TSFE				= $TSFE;
$CFG->table				= array();
$CFG->table['name']		= "be_users";
$CFG->table['username']	= "username";
$CFG->table['password']	= "password";
$CFG->table['uid']		= "uid";
$CFG->table['gid']		= "usergroup";
$CFG->text				= array();
$CFG->text['file']		= t3lib_extMgm::extPath('nwan_webdavserver').'nanoftpd/users';
$CFG->text['sep']		= ":";
$CFG->crypt				= "md5";
$CFG->rootdir 			= t3lib_extMgm::extPath('nwan_webdavserver').'nanoftpd';
$CFG->WEBDAVPREFIX 		= 'webdav';
$CFG->T3PHYSICALROOTDIR = PATH_site;
$CFG->T3ROOTDIR 		= PATH_site.$CFG->WEBDAVPREFIX;
$CFG->T3UPLOADDIR		= "/uploads/tx_metaftpd/";
$CFG->libdir 			= "$CFG->rootdir/lib";
$CFG->moddir 			= "$CFG->rootdir/modules";
$CFG->io				= "file";
$CFG->server_name 		= "ezComponents WebDAV server [Netzweberei]";
$CFG->dynip				= array();
$CFG->dynip['on']		= 0;
$CFG->dynip['iface']	= "ppp0";
$CFG->logging 			= new object;
$CFG->logging->mode		= 1;
$CFG->logging->file		= "$CFG->rootdir/log/nanoftpd.log";
//require($CFG->libdir."/db_".$CFG->dbtype.".php");
//require("$CFG->libdir/pool.php");
//require("$CFG->libdir/auth.php");
//require("$CFG->libdir/log.php");
require_once 'classes'.DS.'class.tx_metaftpd_t3io.php';
//$CFG->pasv_pool 		= new pool();
//$CFG->log 				= new log($CFG);
if ($CFG->dbtype != "text")
{ 
	$CFG->dblink = db_connect($CFG->dbhost, $CFG->dbname, $CFG->dbuser, $CFG->dbpass);
}

$t3io=t3lib_div::makeInstance('tx_metaftpd_t3io');
$t3io->T3Init($CFG);
$CFG->t3io=$t3io;