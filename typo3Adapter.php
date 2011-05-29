<?php
class typo3Adapter {
	protected static $instance;
	protected $properties = array(
		'dbuser'=>null,
		'dbpass'=>null,
		'dbhost'=>null,
		'dbname'=>null,
		'dbtype'=>null,
		'debuglevel'=>null,
		'debugfunction'=>null,
		'T3Page'=>null,
		'T3DB'=>null,
		'T3TCE'=>null,
		'TSFE'=> null,
		'table'=> null,
		'text'=> null,
		'crypt' => null,
		'rootdir' => null,
		'WEBDAVPREFIX' => null,
		'T3PHYSICALROOTDIR' => null,
		'T3ROOTDIR' => null,
		'T3UPLOADDIR' => null,
		'libdir' => null,
		'moddir' => null,
		'io' => null,
		'server_name' => null,
		'dynip' => null,
		'logging' => null,
	);

	public static function getInstance()
	{
		if ( self::$instance === null )
		{
			self::$instance = new typo3Adapter();
			self::$instance->init();
		}
		return self::$instance;
	}

	protected function construct()
	{
		$this->dbtype 	= 'mysql';
		$this->table 	= array(
			'name' 		=> 'be_users',
			'username'	=> 'username',
			'password'	=> 'password',
			'uid'		=> 'uid',
			'gid'		=> 'usergroup',
		);
		$this->text 	= array(
			'file' 	=> null,
			'sep' 	=> ':',
		);
		$this->crypt 	= 'md5';
		$this->WEBDAVPREFIX = 'webdav';
		$this->T3UPLOADDIR = '/uploads/tx_metaftpd';
		$this->io 		= 'file';
		$this->server_name = 'TYPO3 FTPd server [Metaphore Multimedia]';
		$this->dynip 	= array(
			'on' 	=> 0,
			'iface' => 'ppp0',
		);
		
		$this->logging 			= new object;
		$this->logging->mode	= 1;
		$this->logging->file	= $this->rootdir.'/log/nanoftpd.log';

		$this->debuglevel		= array();
		$this->debugfunction	= array();

	}

	protected function init()
	{
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
		$temp_TSFEclassName = t3lib_div::makeInstanceClassName('tslib_fe');
		$TSFE = new $temp_TSFEclassName
		(
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
			die("Couldn't connect to database at ".__FILE__.', line '.__LINE__);
		}
		$TYPO3_DB->sql_select_db(TYPO3_db);

		$GLOBALS['LANG']->charSet = 'utf-8';

		$this->dbuser 			= TYPO3_db_username;
		$this->dbpass 			= TYPO3_db_password;
		$this->dbhost 			= TYPO3_db_host;
		$this->dbname 			= TYPO3_db;
		$this->T3PAGE			= $TYPO3_PAGE;
		$this->T3DB				= $TYPO3_DB;
		$this->T3TCE			= $TYPO3_TCE;
		$this->TSFE				= $TSFE;
		$this->text['file']		= t3lib_extMgm::extPath('nwan_webdavserver').'nanoftpd/users';
		$this->rootdir 			= t3lib_extMgm::extPath('nwan_webdavserver').'nanoftpd';
		$this->T3PHYSICALROOTDIR = PATH_site;
		$this->T3ROOTDIR 		= PATH_site.$this->WEBDAVPREFIX;
		$this->libdir 			= "$this->rootdir/lib";
		$this->moddir 			= "$this->rootdir/modules";
		
		require($this->libdir."/db_".$this->dbtype.".php");
		require($this->libdir."/pool.php");
		require($this->libdir."/auth.php");
		require($this->libdir."/log.php");
		require_once 'classes'.DS.'class.tx_metaftpd_t3io.php';
		
		$this->pasv_pool 		= new pool();
		$this->log 				= new log($this);
		$this->dblink = db_connect($this->dbhost, $this->dbname, $this->dbuser, $this->dbpass);
	}

	public function __get($propertyName)
	{
		if ( $this->__isset($propertyName) === true )
		{
			return $this->properties[$propertyName];
		}
		throw new ezcBasePropertyNotFoundException($propertyName);
	}

	public function __set($propertyName, $propertyValue)
	{
		switch( $propertyName)
		{
			case 'fakeLiveProperties':
				throw new ezcBaseValueException($propertyName, $propertyValue, 'fakeLiveProperties is "read only"');
				break;
			default:
				throw new ezcBasePropertyNotFoundException($propertyName);
		}
		$this->properties[$propertyName] = $propertyValue;
	}

	public function __isset($propertyName)
	{
		return array_key_exists(
		$propertyName,
		$this->properties
		);
	}
}