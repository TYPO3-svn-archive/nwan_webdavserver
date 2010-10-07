<?php
class t3WebdavHybridBackend extends ezcWebdavMemoryBackend implements ezcWebdavLockBackend {
	
	protected $CFG;
	
	public function __construct( $CFG )
    {
        $this->CFG = $CFG;
        $this->fakeLiveProperties = false;

        parent::__construct();
    }
    
	/**
     * Return an initial set of properties for resources and collections.
     *
     * The second parameter indicates wheather the given resource is a
     * collection. The returned properties are used to initialize the property
     * arrays for the given content.
     * 
     * @param string $name
     * @param bool $isCollection
     * @return array
     *
     * @access protected
     */
	public function initializeProperties( $name, $isCollection = false )
    {
        if ( $this->fakeLiveProperties )
        {
            $propertyStorage = new ezcWebdavBasicPropertyStorage();
            
            $isCollection = $this->CFG->t3io->T3IsDir($this->CFG->t3io->_slashify($name));
			
            // Add default creation date
            $ctime = $this->CFG->t3io->T3FileCTime(
            	$isCollection ?
            	$this->CFG->t3io->_slashify($name) :
            	$name
            );
            $ctime = $ctime ? $ctime  : '1054064820';
            $propertyStorage->attach(
                new ezcWebdavCreationDateProperty( new ezcWebdavDateTime( '@'.$ctime ) )
            );

            // Define default display name
            $propertyStorage->attach(
                new ezcWebdavDisplayNameProperty( basename( urldecode( $name ) ) )
            );

            // Define default language
            $propertyStorage->attach(
                new ezcWebdavGetContentLanguageProperty( array( 'en' ) )
            );

            // Define default content type
            $propertyStorage->attach(
                new ezcWebdavGetContentTypeProperty( 
                    $isCollection ? 'httpd/unix-directory' : 'application/octet-stream'
                )
            );

            // Define default ETag
            $propertyStorage->attach(
                new ezcWebdavGetEtagProperty( $this->getETag( $name ) )
            );

            // Define default modification time
            $mtime = $this->CFG->t3io->T3FileMTime(
				$isCollection ?
            	$this->CFG->t3io->_slashify($name) :
            	$name
            );
            $mtime = $mtime ? $mtime  : time();
            $propertyStorage->attach(
                new ezcWebdavGetLastModifiedProperty( new ezcWebdavDateTime( '@'.$mtime ) )
            );

            // Define content length if node is a resource.
            $propertyStorage->attach(
                new ezcWebdavGetContentLengthProperty(
                    $isCollection ?
                        ezcWebdavGetContentLengthProperty::COLLECTION :
                        (string) $this->CFG->t3io->T3FileSize(
                        	$isCollection ?
                        	$this->CFG->t3io->_slashify($name) :
                        	$name
                ))
            );

            $propertyStorage->attach(
                new ezcWebdavResourceTypeProperty(
                    ( $isCollection === true ? 
                        ezcWebdavResourceTypeProperty::TYPE_COLLECTION : 
                        ezcWebdavResourceTypeProperty::TYPE_RESOURCE
                    )
                )
            );
        }
        else
        {
            $propertyStorage = new ezcWebdavBasicPropertyStorage();
        }

        return $propertyStorage;
    }
    
	/**
     * Create a new collection.
     *
     * Creates a new collection at the given path.
     * 
     * @param string $path 
     * @return void
     */
    protected function createCollection( $path )
    {
    	$t3io=$this->CFG->t3io;
		
    	$path=$t3io->T3CleanFilePath($path);
		$info=$t3io->T3IsFile($path);
		
		if ($info['isWebmount']) 
		{	
			$parent = dirname($path);
			$name   = basename($path);

			$page['pid']=$info['pid'];
			$page['title']=$name;
			$this->CFG->T3DB->exec_INSERTquery('pages',$page);
			$t3io->T3ClearPageCache($page['pid']);
			$t3io->T3ClearAllCache();
			
		} 
		else 
		{
			
			$path=$t3io->T3MakeFilePath($path);
			$path=$t3io->T3CleanFilePath($path);
			
			$parent = dirname($path);
			$name   = basename($path);
			 
			$t3io->metaftpd_devlog(1,"=== MKCOL inter : path $path : parent $parent :  name $name",'t3WebdavMemoryBackend::createCollection($path)',"MKCOL");
			
			// TODO: throw exeptions instead of returning simple text-messages
			if (!$t3io->T3FileExists($parent)) 
			{
				$t3io->metaftpd_devlog(1,"=== MKCOL END : 409 ",'t3WebdavMemoryBackend::createCollection($path)',"MKCOL");
				//return "409 Conflict";
			}

			if (!$t3io->T3IsDir($parent)) 
			{
				$t3io->metaftpd_devlog(1,"=== MKCOL END : 403 1",'t3WebdavMemoryBackend::createCollection($path)',"MKCOL");
				//return "403 Forbidden";
			}

			if ($t3io->T3FileExists($parent."/".$name) ) 
			{
				$t3io->metaftpd_devlog(1,"=== MKCOL END : 405 $parent - $name",'t3WebdavMemoryBackend::createCollection($path)',"MKCOL");
				//return "405 Method not allowed";
			}

			if (!empty($_SERVER["CONTENT_LENGTH"])) 
			{ 	
				// no body parsing yet
				$t3io->metaftpd_devlog(1,"=== MKCOL END : 415",'t3WebdavMemoryBackend::createCollection($path)',"MKCOL");
				//return "415 Unsupported media type";
			}
			 
			$stat = mkdir($parent."/".$name, 0777);
			if (!$stat) 
			{
				$t3io->metaftpd_devlog(1,"=== MKCOL END : 403",'t3WebdavMemoryBackend::createCollection($path)',"MKCOL");
				//return "403 Forbidden";
			} 
			else 
			{
				$t3io->metaftpd_devlog(1,"=== MKCOL END 201",'t3WebdavMemoryBackend::createCollection($path)',"MKCOL");
			}
		}
    }
    
    
    /**
     * Create a new resource.
     *
     * Creates a new resource at the given path, optionally with the given
     * content.
     * 
     * @param string $path 
     * @param string $content 
     * @return void
     */
    protected function createResource( $path, $content = null )
    {
    	$t3io=$this->CFG->t3io;

    	$virtualFilePath = $t3io->T3CleanFilePath($path);

    	// We have a conflict if filename is identical to dir name ..
    	if (!@$t3io->T3IsDir(dirname($virtualFilePath)))
    	{
    		$t3io->metaftpd_devlog(1,"=== PUT START 409 Conflict",'t3WebdavMemoryBackend::createResource($path)',"PUT");
    	}

    	$virtualFilePath=$t3io->T3MakeFilePath($virtualFilePath);
    	$info=$t3io->T3IsFileUpload($path);
    	 
    	if ($info['isWebmount'])
    	{
    		// Web mount
    		$info['ufile']=$virtualFilePath;
    		$t3io->metaftpd_devlog(6,"PUT info : ".serialize($info),"meta_webdav","PUT");
    			
    		// in update mode we update directly tx_metaftpd_ftpfile ... (No copy)
    		// we can integrate versionning here later ...
    		$physicalFilePath=$t3io->T3CleanFilePath($this->CFG->T3PHYSICALROOTDIR.$info['filepath']);
    		$t3io->metaftpd_devlog(6,"PUT physicalFilePath : ".$physicalFilePath,"meta_webdav","PUT");
    			
    		file_put_contents($physicalFilePath,$content);
    			
    		$t3io->T3LinkFileUpload($info);
    			
    		//unlink($physicalFilePath);

    		// Set initial metadata for collection
    		//$this->props[$path] = $this->initializeProperties( $path, false );

    	}
    	else
    	{
    			
    		// File Mount:
    		//$this->options = new ezcWebdavFileBackendOptions();
    		$physicalFilePath=$virtualFilePath;
    			
    		file_put_contents($physicalFilePath,$content);
    		chmod( $physicalFilePath, 0755 );

    		// This automatically creates the property storage if missing
    		//$storage = $this->getPropertyStoragePath( $path );
    	}
    }

    /**
     * Get contents of a resource.
     * 
     * @param string $path 
     * @return string
     */
    protected function getResourceContents( $path )
    {
    	$t3io=$this->CFG->t3io;

    	// get absolute fs path to requested resource
    	$virtualpath = $t3io->T3CleanFilePath($path);

//    	// sanity check
//    	if (!$t3io->T3FileExists($path)) 
//    	{
//    		return false;
//    	}
    		
//    	// is this a collection?
//    	if ($t3io->T3IsDir($virtualpath)) 
//    	{
//    		return $this->Getdir($virtualpath, $options);
//    	}

//    	// detect resource type
//    	$options['mimetype'] = $this->_mimetype($virtualpath);

//    	// detect modification time
//    	// see rfc2518, section 13.7
//    	// some clients seem to treat this as a reverse rule
//    	// requiering a Last-Modified header if the getlastmodified header was set
//    	$options['mtime'] = $t3io->T3FileMTime($virtualpath);

//    	// detect resource size
//    	$options['size'] = $t3io->T3FileSize($virtualpath);

    	// no need to check result here, it is handled by the base class
    	$info=$t3io->T3IsFile($virtualpath);
//    	$GLOBALS['data']=$info['data'];

    	if ($info['isWebmount']) 
    	{
    		// If file was uploaded through metaftpd we link to it
    		if ($info['tx_metaftpd_ftpfile']) 
    		{
    			$streamPath=$t3io->T3MakeFilePath('/uploads/tx_metaftpd/'.$info['tx_metaftpd_ftpfile']);
    			return file_get_contents($streamPath);
    		} 
    		else 
    		{
    			// otherwise we open bodytext data
    			return $info['data'];
    		}
    	} 
    	else 
    	{
    		$streamPath=$t3io->T3MakeFilePath($virtualpath);
    		return file_get_contents($streamPath);
    	}
    }

    /**
     * Sets the contents of a resource.
     *
     * This method replaces the content of the resource identified by $path
     * with the submitted $content.
     *
     * @param string $path
     * @param string $content
     * @return void
     */
    protected function setResourceContents( $path, $content )
    {
    	// File Mount:
    	$t3io=$this->CFG->t3io;

    	$virtualFilePath = $t3io->T3CleanFilePath($path);
    	$virtualFilePath=$t3io->T3MakeFilePath($virtualFilePath);

    	$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['meta_ftpd']['natural_file_names'] = true;
    	$info=$t3io->T3IsFileUpload($path);

    	if ($info['isWebmount'])
    	{
    		// Web mount
    		$info['ufile']=$virtualFilePath;
    		$t3io->metaftpd_devlog(6,"PUT info : ".serialize($info),"meta_webdav","PUT");
    			
    		// in update mode we update directly tx_metaftpd_ftpfile ... (No copy)
    		// we can integrate versionning here later ...
    		$physicalFilePath=$t3io->T3CleanFilePath($this->CFG->T3PHYSICALROOTDIR.$info['filepath']);
    		$t3io->metaftpd_devlog(6,"PUT physicalFilePath : ".$physicalFilePath,"meta_webdav","PUT");
    			
    		file_put_contents($physicalFilePath,$content);
    			
    		$t3io->T3LinkFileUpload($info);
    			
    		unlink($physicalFilePath);

    		// Set initial metadata for collection
    		$this->props[$path] = $this->initializeProperties( $path, false );

    	}
    	else
    	{
    		$physicalFilePath=$virtualFilePath;
    		 
    		file_put_contents( $physicalFilePath, $content );
    		 
    		chmod( $physicalFilePath, 0755 );
    	}
    }

    /**
     * Delete everything below this path.
     *
     * Returns an error response if the deletion failed, and null on success.
     *
     * @param string $path
     * @return ezcWebdavErrorResponse
     */
    protected function performDelete( $path )
    {
    	$t3io=$this->CFG->t3io;
    	$info=$t3io->T3IsFile($path);
    	// TODO: check, probably we dont need to call T3CleanFilePath!
    	$path =$t3io->T3CleanFilePath($path);

    	/*
    	// Check if any errors would occur during deletion process
    	$error = array();
    	foreach ( $this->content as $name => $content )
    	{
    		if ( strpos( $name, $path ) === 0 && ( substr( $name, strlen( $path ), 1 ) === '/' || $name === $path ) )
    		{
    			// Check if we want to cause some errors here.
    			if ( $this->options->failingOperations & ezcWebdavMemoryBackendOptions::REQUEST_DELETE && preg_match( $this->options->failForRegexp, $name ) > 0 )
    			{
    				$error[] = new ezcWebdavErrorResponse(
    				ezcWebdavResponse::STATUS_423,
    				$name
    				);
    			}
    		}
    	}
    	*/

    	if ($info['isWebmount']) 
    	{
    		$arr=array('deleted'=>'1');
    		if (!$info['isWebcontent']) $this->CFG->T3DB->exec_UPDATEquery('pages',"uid='".$info['uid']."'",$arr);
    		if ($info['isWebcontent']) $this->CFG->T3DB->exec_UPDATEquery('tt_content',"uid='".$info['uid']."'",$arr);
    		$t3io->T3ClearPageCache($info['pid']);
    		$t3io->T3ClearAllCache();
    	} 
    	else 
    	{
    		// File mount
    		if (!$t3io->T3FileExists($path)) {
    			$error[] = new ezcWebdavErrorResponse(
    				ezcWebdavResponse::STATUS_404,
    				$name
    			);
    		}
    		$path=$t3io->T3MakeFilePath($path);

    		if ($t3io->T3IsDir($path)) {
    			$query = "DELETE FROM {$this->db_prefix}properties WHERE path LIKE '".$this->_slashify($path)."%'";
    			$this->CFG->T3DB->exec_DELETEquery('tx_metaftpd_webdav_properties',"path LIKE '".$this->_slashify($path)."%'");
    			
    			// we allow deletion only in specified directories ...
    			if (strpos($path, $this->CFG->T3PHYSICALROOTDIR.'litmus')!==false || strpos($path, $this->CFG->T3PHYSICALROOTDIR.'fileadmin')!==false || strpos($path, $this->CFG->T3PHYSICALROOTDIR.'uploads/tx_metaftpd')!==false) 
    			{
    				System::rm("-rf $path");
    			}
    		} 
    		else 
    		{
    			unlink($path);
    		}
    		$query = "DELETE FROM {$this->db_prefix}properties WHERE path = '$path'";
    		$this->CFG->T3DB->exec_DELETEquery('tx_metaftpd_webdav_properties',"path = '$path'");
    	}

    	return null;
    }
    
    /**
     * Returns if a resource exists.
     *
     * Returns if a the resource identified by $path exists.
     * 
     * @param string $path 
     * @return bool
     */
    public function nodeExists( $path )
    { 	    	
    	//return $this->CFG->t3io->T3FileExists($this->CFG->t3io->_slashify($path));
    	return $this->CFG->t3io->T3FileExists($path);
    }
}
?>