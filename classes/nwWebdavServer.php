<?
class nwWebdavServer {
	
	protected static $instance;
	
	private $ezcServer;
	private $ezcPathFactory;
	
	private $nwBackend;
	private $nwAuth;
	
	private $t3Path;
	private $t3CurrDir = array();
	private $encode_t3CurrDirNodes = false;
	
	private $CFG;
	private $base;
	
	public static function getInstance($CFG)
	{
		if(self::$instance === null)
		{
			self::$instance = new nwWebdavServer();
			self::$instance->init($CFG);
		}
		return self::$instance;
	}
	
	protected function __construct()
	{
		
		$this->nwAuth = new nwWebdavAuth();
		
		$this->ezcServer = ezcWebdavServer::getInstance();
		 
	}
	
	protected function init($CFG)
	{
		// some remains of tx_meta_ftpd...
		define('T3_FTPD_WWW_ROOT','WEBMOUNTS');
		define('T3_FTPD_FILE_ROOT','FILEMOUNTS');
		$this->CFG = $CFG;
		
		// get some base info
		$this->CFG->t3io->metaftpd_devlog(1,'$_SERVER:'.print_r($_SERVER, true) ,basename(__FILE__).':'.__LINE__,"ServeRequest");
		
        // init user, use for authorisation
		$this->CFG = $this->nwAuth->CFG = $this->CFG;
		
        // init webdav server
		$this->ezcServer->auth = $this->nwAuth;
		
        // init base path
		$this->base = (@$_SERVER["HTTPS"] === "on" ? "https:" : "http:").'//'.$_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME'];
		$this->ezcPathFactory = new ezcWebdavBasicPathFactory($this->base);		
		foreach ( $this->ezcServer->configurations as $conf ){
		    $conf->pathFactory = $this->ezcPathFactory;
		}
		
		// init virtual path to typo3-memory
		$this->t3Path = str_replace($_SERVER['SCRIPT_NAME'], '', $_SERVER['REQUEST_URI']);
		if(
			// TODO: t3lib_div::isFirstPartOfString($this->t3Path, '/'.T3_FTPD_FILE_ROOT)
			strpos($this->t3Path, '/'.T3_FTPD_FILE_ROOT)!==0
			&& strpos($this->t3Path, '/'.T3_FTPD_WWW_ROOT)!==0
		)
		{
			$this->t3Path = '/';
		}
		
		// init backend				
		$this->nwBackend = new nwWebdavBackend($this->CFG);

		// init typo3 user, use for vfs-setup
		// TODO: create t3User-Class, use ezcCache to store userdetails 
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
		
		$this->t3CurrDir = array();	
	}
	
	public function serve()
	{
		// get curr dir's contents
		$_currDirContents = $this->CFG->t3io->T3ListDir($this->t3Path);
		$this->CFG->t3io->metaftpd_devlog(5,'$_currDirContents:'.print_r($_currDirContents, true) ,basename(__FILE__).':'.__LINE__,"ServeRequest");
		
		if(is_array($_currDirContents))
		{
			//turn every leaf into a collection
			foreach($_currDirContents as $_node )
			{	
				$this->CFG->t3io->metaftpd_devlog(5,'->$_node:'.$this->t3Path.$_node ,basename(__FILE__).':'.__LINE__,"ServeRequest");
				$this->t3CurrDir[$this->nodeEncode($_node)] = $this->CFG->t3io->T3IsDir($this->t3Path.$_node) ? array() : $this->nodeEncode($_node);
			}
		}
		
		$this->CFG->t3io->metaftpd_devlog(5,'$this->t3CurrDir:'.print_r($this->t3CurrDir, true) ,basename(__FILE__).':'.__LINE__,"ServeRequest");
		
		// append the curr dir to the whole path
		foreach(array_reverse(explode('/', $this->t3Path)) as $_treenode){
			
			if($_treenode != '' && !isset($this->t3CurrDir[$_treenode])){ 		
				$$_treenode = array($_treenode => $this->t3CurrDir);
				$this->t3CurrDir = $$_treenode;
			}
		}

		// add contents to backend
		$this->CFG->t3io->metaftpd_devlog(5,'$this->t3CurrDir:'.print_r($this->t3CurrDir, true) ,basename(__FILE__).':'.__LINE__,"ServeRequest");
		$this->nwBackend->addContents(
			$this->t3CurrDir
		);
		
		// wait for requests
		$this->ezcServer->handle( $this->nwBackend );
	}
	
	/**
	 * Enter description here ...
	 * @param string $t3ListDir_node
	 */
	private function nodeEncode($t3ListDir_node)
	{
		if($this->encode_t3CurrDirNodes === true)
		{
			return rawurlencode($t3ListDir_node);
		}
		
		return $t3ListDir_node;
	}
}