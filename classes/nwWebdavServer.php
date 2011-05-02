<?
class nwWebdavServer {
	
	protected static $instance;
	
	private $ezcServer;
	private $ezcPathFactory;
	
	private $nwBackend;
	private $nwAuth;
	private $nwStorageDir = 'storage';
	private $nwTokensStorage;
	private $nwTokensStorageFile = 'tokens.php';
	
	private $t3Path;
	private $t3CurrDir = array();
	private $encode_t3CurrDirNodes = true;
	
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
		$this->nwTokensStorage = NWAN_WEBDAVSERVER_ROOT.DS.$this->nwStorageDir.DS.$this->nwTokensStorageFile;
		$this->nwAuth = new nwWebdavAuth(
			$this->nwTokensStorage
		);
		
		$this->ezcServer = ezcWebdavServer::getInstance();
	}
	
	protected function init($CFG)
	{
		// some remains of tx_meta_ftpd...
		define('T3_FTPD_WWW_ROOT','WEBMOUNTS');
		define('T3_FTPD_FILE_ROOT','FILEMOUNTS');
		$this->CFG = $CFG;
		
		// get some base info
		$this->CFG->t3io->metaftpd_devlog(1,"init(%CFG)" ,__METHOD__, get_defined_vars());
		
        // init user, use for authorisation
		$this->CFG = $this->nwAuth->CFG = $this->CFG;
		
        // init webdav server
		$this->ezcServer->auth = $this->nwAuth;
		
		// init locking
		$this->ezcServer->pluginRegistry->registerPlugin(
			new ezcWebdavLockPluginConfiguration()
		);
		
        // init base path
		$this->base = (@$_SERVER["HTTPS"] === "on" ? "https:" : "http:").'//'.$_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME'];
		$this->ezcPathFactory = new ezcWebdavBasicPathFactory($this->base);		
		foreach ( $this->ezcServer->configurations as $conf )
		{
		    $conf->pathFactory = $this->ezcPathFactory;
		}
		
		// init virtual path to typo3-memory
		$this->t3Path = $this->processURL($_SERVER['REQUEST_URI']);
		
		// init backend				
		$this->nwBackend = new nwWebdavBackend($this->CFG, $this->nwTokensStorage);

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
//		$this->CFG->t3io->metaftpd_devlog(1,'()' ,__METHOD__, array($this->t3Path) );
//		
//		$pass1 = $this->CFG->t3io->isT3($this->t3Path);
//		$this->CFG->t3io->metaftpd_devlog(1,'$path isT3 ?' ,__METHOD__, $pass1);
//		
//		if( $pass1['isWebmount'] || $pass1['isFilemount'] )
//		{
//			$pass2 = $this->CFG->t3io->T3IsDir($this->t3Path);
//			
//			$this->CFG->t3io->metaftpd_devlog(1,'$path is a dir ?' ,__METHOD__, array('$pass2'=>$pass2));
//			
//			if( $pass2 )
//			{
//				$this->t3Path = $this->CFG->t3io->_slashify($this->t3Path);
//			}
//		}
//		
//		if(substr($this->t3Path, -1) == DS) // current node is a dir
//		{		
//			// get curr dir's contents
//			$_currDirContents = $this->CFG->t3io->T3ListDir($this->t3Path);
//			
//			if(is_array($_currDirContents))
//			{
//				//turn every leaf into a collection
//				foreach($_currDirContents as $_node )
//				{	
//	//				$this->CFG->t3io->metaftpd_devlog(1,'foreach($_currDirContents as $_node )' ,__METHOD__, get_defined_vars());
//					$this->t3CurrDir[$this->nodeEncode($_node)] = $this->CFG->t3io->T3IsDir($this->t3Path.$_node) ? array() : $this->nodeEncode($_node);
//				}
//			}
//			
//			$this->CFG->t3io->metaftpd_devlog(1,'just built $this->t3CurrDir:',__METHOD__, get_defined_vars());
//			
//			// append the curr dir to the whole path
//			foreach(array_reverse(explode('/', $this->t3Path)) as $_treenode){
//				
//				if($_treenode != '' && !isset($this->t3CurrDir[$_treenode])){ 		
//					$$_treenode = array($_treenode => $this->t3CurrDir);
//					$this->t3CurrDir = $$_treenode;
//				}
//			}
//		}
//
//		// add contents to backend
//		$this->CFG->t3io->metaftpd_devlog(1,'just appended $this->t3CurrDir:',__METHOD__, get_object_vars($this));
//		
//		$this->nwBackend->addContents(
//			$this->t3CurrDir
//		);
		
		// ensure a clean output
		ob_clean(); 
		
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
	
	protected function processURL( $url )
	{
		$processedUrl = str_replace($_SERVER['SCRIPT_NAME'], '', $url);
		
		if(
			// TODO: t3lib_div::isFirstPartOfString($this->t3Path, '/'.T3_FTPD_FILE_ROOT)
			strpos($processedUrl, '/'.T3_FTPD_FILE_ROOT)!==0
			&& strpos($processedUrl, '/'.T3_FTPD_WWW_ROOT)!==0
		)
		{
			$processedUrl = DS;
		}
		
		$this->CFG->t3io->metaftpd_devlog(1,'',__METHOD__, get_defined_vars());
		
		return $processedUrl;
	} 
}