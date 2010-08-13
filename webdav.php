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
	
	function __construct($CFG){
		
        // init typo3 user, use for authorisation
		$this->t3Auth = new  t3Auth($CFG);
        $this->CFG = $this->t3Auth->initT3User($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
		
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
		if($t3path=='')
			$t3path = '/';

		// init memory backend
		switch(strpos($t3path, 'FILEMOUNTS')>0){
//			case true:
//			case '1':
//				$this->t3Backend = new ezcWebdavFileBackend('/Users/andreas/Sites/dummy-4.3.3/fileadmin');
//				break;
			
			default:
				
				$this->t3Backend = new t3WebdavHybridBackend($this->CFG);

				// get curr dir's contents
				$this->t3CurrDir = array();
				$_currDirContents = $this->CFG->t3io->T3ListDir($t3path);
				$this->CFG->t3io->metaftpd_devlog(5,'$_currDirContents:'.print_r($_currDirContents, true) ,'nwan_webdavserver::__construct($CFG)',"ServeRequest");
				
				//turn every leaf into a collection
				foreach($_currDirContents as $_node ){ 
					
					$this->CFG->t3io->metaftpd_devlog(5,'->$_node:'.$t3path.$_node ,'nwan_webdavserver::__construct($CFG)',"ServeRequest");
					$this->t3CurrDir[$_node] = $this->CFG->t3io->T3IsDir($t3path.$_node) ? array() :$_node;
				}
				
				$this->CFG->t3io->metaftpd_devlog(5,'$this->t3CurrDir:'.print_r($this->t3CurrDir, true) ,'nwan_webdavserver::__construct($CFG)',"ServeRequest");
				
				// append the curr dir to the whole path
				foreach(array_reverse(explode('/', $t3path)) as $_treenode){
					
					if($_treenode != '' && !isset($this->t3CurrDir[$_treenode])){ 		
						$$_treenode = array($_treenode => $this->t3CurrDir);
						$this->t3CurrDir = $$_treenode;
					}
				}
		
				// add contents to backend
				$this->CFG->t3io->metaftpd_devlog(5,'$this->t3CurrDir:'.print_r($_SESSION['t3CurrDir'], true) ,'$this->t3Backend->addContents($this->t3CurrDir)',"ServeRequest");
				$this->t3Backend->addContents(
					$this->t3CurrDir
				);
						
		}
		
		// wait for requests
		$this->ezcServer->handle( $this->t3Backend ); 
	}
}
?>