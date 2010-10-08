<?
date_default_timezone_set('Europe/Berlin');

define('DS', DIRECTORY_SEPARATOR);
define('PS', PATH_SEPARATOR);
define('NWAN_DEBUGURL', '192.168.2.101');
define('NWAN_DEBUGMODE', 'ON');
define('CANDEBUG', ($_SERVER['REMOTE_ADDR']==NWAN_DEBUGURL && NWAN_DEBUGMODE=='ON') );


require_once dirname(__FILE__)."/config.php";
require_once 'tutorial_autoload.php';
require_once 'custom_auth.php';
require_once 'backends/class.t3WebdavHybridBackend.php';

$webdav = new nwan_webdavserver($CFG);

class nwan_webdavserver {
	
	private $ezcServer;
	private $ezcPathFactory;
	
	private $t3CurrDir = array();
	private $t3ContentLeaves;
	private $t3Backend;
	private $t3Auth;
	
	private $CFG;
	private $base;
	
	function __construct($CFG)
	{
		$this->CFG = $CFG;
		
		// get some base info
		$this->CFG->t3io->metaftpd_devlog(1,'$_SERVER:'.print_r($_SERVER, true) ,basename(__FILE__).':'.__LINE__,"ServeRequest");
		
        // init typo3 user, use for authorisation
		$this->t3Auth = new t3Auth($this->CFG);
		
        // init webdav server
		$this->ezcServer = ezcWebdavServer::getInstance();
		$this->ezcServer->auth = $this->t3Auth;
		
        // init base path
		$this->base = (@$_SERVER["HTTPS"] === "on" ? "https:" : "http:").'//'.$_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME'];
		$this->ezcPathFactory = new ezcWebdavBasicPathFactory($this->base);		
		foreach ( $this->ezcServer->configurations as $conf ){
		    $conf->pathFactory = $this->ezcPathFactory;
		}
		
		// init virtual path to typo3-memory
		$t3path = str_replace($_SERVER['SCRIPT_NAME'], '', $_SERVER['REQUEST_URI']);
		
		$this->CFG->t3io->metaftpd_devlog(5,'$t3path-1:'.print_r($t3path, true) ,basename(__FILE__).':'.__LINE__,"ServeRequest");
		
		if(
			strpos($t3path, '/'.T3_FTPD_FILE_ROOT)!==0
			&& strpos($t3path, '/'.T3_FTPD_WWW_ROOT)!==0
		){
			$t3path = '/';
		}

		// init backend				
		$this->t3Backend = new t3WebdavHybridBackend($this->CFG);

		$this->CFG->t3io->metaftpd_devlog(5,'$t3path-2:'.print_r($t3path, true) ,basename(__FILE__).':'.__LINE__,"ServeRequest");

		// identify current user
		if($_SERVER['PHP_AUTH_DIGEST'])
		{
			preg_match('/username="([^"]*)"/', $_SERVER['PHP_AUTH_DIGEST'], $match_username);
			$this->CFG->t3io->metaftpd_devlog(5,'$match_username:'.print_r($match_username, true) ,basename(__FILE__).':'.__LINE__,"ServeRequest");
			
			$username = $match_username[1];
		}
		else 
		{
			$username = $_SERVER['PHP_AUTH_USER'];
		}
		$this->CFG->t3io->T3Identify($username);
		
		// get curr dir's contents
		$this->t3CurrDir = array();
		$_currDirContents = $this->CFG->t3io->T3ListDir($t3path);
		$this->CFG->t3io->metaftpd_devlog(5,'$_currDirContents:'.print_r($_currDirContents, true) ,basename(__FILE__).':'.__LINE__,"ServeRequest");
		
		if(is_array($_currDirContents))
		{
			//turn every leaf into a collection
			foreach($_currDirContents as $_node )
			{	
				$this->CFG->t3io->metaftpd_devlog(5,'->$_node:'.$t3path.$_node ,basename(__FILE__).':'.__LINE__,"ServeRequest");
				// TODO: BUGFIX needed here: t3io->T3MakeFilePath!!!
				$this->t3CurrDir[$_node] = $this->CFG->t3io->T3IsDir($t3path.$_node) ? array() : $_node;
			}
		}
		
		$this->CFG->t3io->metaftpd_devlog(5,'$this->t3CurrDir:'.print_r($this->t3CurrDir, true) ,basename(__FILE__).':'.__LINE__,"ServeRequest");
		
		// append the curr dir to the whole path
		foreach(array_reverse(explode('/', $t3path)) as $_treenode){
			
			if($_treenode != '' && !isset($this->t3CurrDir[$_treenode])){ 		
				$$_treenode = array($_treenode => $this->t3CurrDir);
				$this->t3CurrDir = $$_treenode;
			}
		}

		// add contents to backend
		$this->CFG->t3io->metaftpd_devlog(5,'$this->t3CurrDir:'.print_r($this->t3CurrDir, true) ,basename(__FILE__).':'.__LINE__,"ServeRequest");
		$this->t3Backend->addContents(
			$this->t3CurrDir
		);
		
		// wait for requests
		$this->ezcServer->handle( $this->t3Backend ); 
	}
}
?>