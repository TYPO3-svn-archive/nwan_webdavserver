<?php
class nwWebdavBackend 
extends ezcWebdavSimpleBackend 
implements ezcWebdavLockBackend 
{
	/**
     * Content structure of memory backend
     * 
     * @var array
     */
    protected $content = array(
        '/' => array(),
    );
    
    /**
     * Properties for collections and resources.
     *
     * They are stored in an array of the following form reusing the initial
     * content example:
     *
     *  array(
     *      '/foo' => array(
     *          'property name' => 'property value',
     *      ),
     *      '/bar' => array(),
     *      '/bar/blubb' => array(),
     *      ...
     *  )
     * 
     * @var array
     */
    protected $properties = array();

    /**
     * Names of live properties from the DAV: namespace which will be handled
     * live, and should not be stored like dead properties.
     * 
     * @var array(int=>string)
     */
    protected $handledLiveProperties = array( 
        'getcontentlength', 
        'getlastmodified', 
        'creationdate', 
        'displayname', 
        'getetag', 
        'getcontenttype', 
        'resourcetype',
        'supportedlock',
        'lockdiscovery',
    );
    
	protected $options = array(
		'CFG' 					=> null,
		'fakeLiveProperties' 	=> false,
		'tokensStorageFile' 	=> null,
		'lockLevel'				=> 0,
		'lockFilename'			=> '.ezc_lock',
		'propertyStoragePath'	=> '.ezc',
		'noLock'				=> false,
		'waitForLock'			=> 200000,
		'lockTimeout'			=>  2,
		'directoryMode'			=> 0755,
		'fileMode'				=> 0644,
	);
	
	protected $lockFile = '';
	
	public function __get($optionName)
	{
		if ( $this->__isset($optionName) === true )
		{
			return $this->options[$optionName];
		}
		throw new ezcBasePropertyNotFoundException($optionName);
	}
	
	public function __set($optionName, $optionValue)
	{
		switch( $optionName)
		{
			case 'CFG':
				if(
					!$optionValue->t3io
					|| is_a($optionValue->t3io, 'tx_metaftpd_t3io') === false
				)
				{
					throw new ezcBaseValueException($optionName, $optionValue, 'tx_metaftpd_t3io not found');
				}
				break;
			case 'fakeLiveProperties':
			case 'waitForLock':
			case 'lockTimeout':
			case 'lockFilename':
			case 'propertyStoragePath':
			case 'directoryMode':
			case 'fileMode':
				throw new ezcBaseValueException($optionName, $optionValue, $optionName.' is "read only"');
				break;
			case 'tokensStorageFile':
			case 'lockLevel':
			case 'noLock':
				break;
			default:
				throw new ezcBasePropertyNotFoundException($optionName);
		}
		$this->options[$optionName] = $optionValue;
	}
	
	public function __isset($optionName)
	{
		return array_key_exists(
			$optionName,
			$this->options
		);
	}
	
	public function __construct($CFG, $tokensStorageFile=null)
    {
        $this->CFG = $CFG;
        $this->tokensStorageFile = $tokensStorageFile;
        
        // share common storage-dir for locking
    	$this->lockFile = dirname($this->tokensStorageFile) . DS . $this->lockFilename;

        // Initialize properties for root
        if ( $this->fakeLiveProperties )
        {
            $this->props['/'] = $this->initializeProperties( '/', true );
        }
    }
    
	/**
     * Locks the backend.
     *
     * Tries to lock the backend. If the lock is already owned by this process,
     * locking is successful. If $timeout is reached before a lock could be
     * acquired, an {@link ezcWebdavLockTimeoutException} is thrown. Waits
     * $waitTime microseconds between attempts to lock the backend.
     * 
     * @param int $waitTime 
     * @param int $timeout 
     * @return void
     */
    public function lock( $waitTime, $timeout )
    {
    	// Check and raise lockLevel counter
        if ( $this->lockLevel > 0 )
        {
            // Lock already acquired
            ++$this->lockLevel;
            return;
        }
    	
    	$lockStart = microtime( true );

        if ( is_file( $this->lockFile ) && !is_writable( $this->lockFile )
             || !is_file( $this->lockFile ) && !is_writable(dirname( $this->lockFile ) ) )
        {
            throw new ezcBaseFilePermissionException(
                $this->lockFile,
                ezcBaseFileException::WRITE,
                'Cannot be used as lock file.'
            );
        }

        // fopen in mode 'x' will only open the file, if it does not exist yet.
        // Even this is is expected it will throw a warning, if the file
        // exists, which we need to silence using the @
        while ( ( $fp = @fopen( $this->lockFile, 'x' ) ) === false )
        {
            // This is untestable.
            if ( microtime( true ) - $lockStart > $timeout )
            {
                // Release timed out lock
                unlink( $this->lockFile );
                $lockStart = microtime( true );
            }
            else
            {
                usleep( $waitTime );
            }
        }

        // Store random bit in file ... the microtime for example - might prove
        // useful some time.
        fwrite( $fp, microtime() );
        fclose( $fp );

        // Add first lock
        ++$this->lockLevel;
    }

    /**
     * Removes the lock.
     * 
     * @return void
     */
    public function unlock()
    {
        // Remove the lock file
        unlink( $this->lockFile );
    }

    /**
     * Wait and get lock for complete directory tree.
     *
     * Acquire lock for the complete tree for read or write operations. This
     * does not implement any priorities for operations, or check if several
     * read operation may run in parallel. The plain locking should / could be
     * extended by something more sophisticated.
     *
     * If the tree already has been locked, the method waits until the lock can
     * be acquired.
     *
     * The optional second parameter $readOnly indicates wheather a read only
     * lock should be acquired. This may be used by extended implementations,
     * but it is not used in this implementation.
     *
     * @param bool $readOnly
     * @return void
     *
     * @todo The locking mechanism affects the ETag of the base collection. The
     *       ETag is different on each request, which might result in problems
     *       for clients that make extensive use of If-* headers. No client is
     *       known so far, if problems occur here we need to find a solution
     *       for this.
     */
    protected function acquireLock( $readOnly = false )
    {
        if ( $this->noLock )
        {
            return true;
        }
        
        try
        {
            $this->lock( $this->waitForLock, $this->lockTimeout );
        }
        catch ( ezcWebdavLockTimeoutException $e )
        {
            return false;
        }
        return true;
    }

    /**
     * Free lock.
     *
     * Frees the lock after the operation has been finished.
     * 
     * @return void
     */
    protected function freeLock()
    {
        if ( $this->noLock )
        {
            return true;
        }
        
        $this->unlock();
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
            
            if( !$isCollection ) 
            {
            	$isCollection = $this->CFG->t3io->T3IsDir($name);	
            }
            
            if( $isCollection ) 
            {
            	$name = $this->CFG->t3io->_slashify($name);
            }
			
            // Add default creation date
            $ctime = ($ctime = $this->CFG->t3io->T3FileCTime($name)) ? 
            	$ctime :
            	'0000000001';
            	
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
            $mtime = ($mtime = (int) $this->CFG->t3io->T3FileMTime($name)) ? 
            	$mtime :
	            '0000000001';
            	
            $propertyStorage->attach(
                new ezcWebdavGetLastModifiedProperty( new ezcWebdavDateTime( '@'.$mtime ) )
            );

            // Define content length if node is a resource.
            $propertyStorage->attach(
                new ezcWebdavGetContentLengthProperty(
                    $isCollection ?
                        ezcWebdavGetContentLengthProperty::COLLECTION :
                        (string) $this->CFG->t3io->T3FileSize($name)
       			)
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
     * Returns the mime type of a resource.
     *
     * Return the mime type of the resource identified by $path. If a mime type
     * extension is available it will be used to read the real mime type,
     * otherwise the original mime type passed by the client when uploading the
     * file will be returned. If no mimetype has ever been associated with the
     * file, the method will just return 'application/octet-stream'.
     * 
     * @param string $path 
     * @return string
     */
    protected function getMimeType( $path )
    {
        // Check if extension pecl/fileinfo is usable.
        if ( $this->options->useMimeExts && ezcBaseFeatures::hasExtensionSupport( 'fileinfo' ) )
        {
            $fInfo = new fInfo( FILEINFO_MIME );
            $mimeType = $fInfo->file( $this->root . $path );

            // The documentation tells to do this, but it does not work with a
            // current version of pecl/fileinfo
            // $fInfo->close();

            return $mimeType;
        }

        // Check if extension ext/mime-magic is usable.
        if ( $this->options->useMimeExts && 
             ezcBaseFeatures::hasExtensionSupport( 'mime_magic' ) &&
             ( $mimeType = mime_content_type( $this->root . $path ) ) !== false )
        {
            return $mimeType;
        }

        // Check if some browser submitted mime type is available.
        $storage = $this->getPropertyStorage( $path );
        $properties = $storage->getAllProperties();

        if ( isset( $properties['DAV:']['getcontenttype'] ) )
        {
            return $properties['DAV:']['getcontenttype']->mime;
        }

        // Default to 'application/octet-stream' if nothing else is available.
        return 'application/octet-stream';
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
    	
    	$t3io->metaftpd_devlog(2,"( $path )",__METHOD__ );
		
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
		else // path points into FILEMOUNT
		{
			
			$path=$t3io->T3MakeFilePath($path);
			$path=$t3io->T3CleanFilePath($path);
			
			$parent = dirname($path);
			$name   = basename($path);
			 
			$t3io->metaftpd_devlog(2,"LINE".__LINE__, __METHOD__, get_defined_vars() );
			
			// TODO: throw exeptions instead of returning simple text-messages
			if (!$t3io->T3FileExists($parent)) 
			{
				$t3io->metaftpd_devlog(2,"409 Conflict", __METHOD__, get_defined_vars() );
				//return "409 Conflict";
			}

			if (!$t3io->T3IsDir($parent)) 
			{
				$t3io->metaftpd_devlog(2,"403 Forbidden", __METHOD__, get_defined_vars() );
				//return "403 Forbidden";
			}

			if ($t3io->T3FileExists($parent."/".$name) ) 
			{
				$t3io->metaftpd_devlog(2,"405 Method not allowed", __METHOD__, get_defined_vars() );
				//return "405 Method not allowed";
			}

			if (!empty($_SERVER["CONTENT_LENGTH"])) 
			{ 	
				// no body parsing yet
				$t3io->metaftpd_devlog(2,"415 Unsupported media type", __METHOD__, get_defined_vars() );
				//return "415 Unsupported media type";
			}
			 
			$stat = mkdir($parent."/".$name, 0777);
			if (!$stat) 
			{
				$t3io->metaftpd_devlog(2,"403 Forbidden", __METHOD__, get_defined_vars() );
				//return "403 Forbidden";
			} 
			else 
			{
				$t3io->metaftpd_devlog(2,"end", __METHOD__, get_defined_vars() );
			}
		}

        // This automatically creates the property storage
        $storage = $this->getPropertyStoragePath( $path . '/foo' );
		
//		// Add collection to parent node
//        $this->content[dirname( $path )][] = $path;
//
//        // Set initial metadata for collection
//        $this->properties[$path] = $this->initializeProperties( $path, true );
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
    	
    	$t3io->metaftpd_devlog(2,"( $path, $content )", __METHOD__ );

    	$virtualFilePath = $t3io->T3CleanFilePath($path);

    	// We have a conflict if filename is identical to dir name ..
    	if (!@$t3io->T3IsDir(dirname($virtualFilePath)))
    	{
    		$t3io->metaftpd_devlog(2,"PUT START 409 Conflict", __METHOD__, get_defined_vars() );
    	}

    	$virtualFilePath=$t3io->T3MakeFilePath($virtualFilePath);
    	$info=$t3io->T3IsFileUpload($path);
    	 
    	if ($info['isWebmount'])
    	{
    		// Web mount
    		$info['ufile']=$virtualFilePath;
    		$t3io->metaftpd_devlog(2,"PUT Info", __METHOD__, get_defined_vars() );
    			
    		// in update mode we update directly tx_metaftpd_ftpfile ... (No copy)
    		// we can integrate versionning here later ...
    		$physicalFilePath=$t3io->T3CleanFilePath($this->CFG->T3PHYSICALROOTDIR.$info['filepath']);
    		$t3io->metaftpd_devlog(2,"PUT $physicalFilePath", __METHOD__, get_defined_vars() );
    			
    		file_put_contents($physicalFilePath,$content);
    			
    		$t3io->T3LinkFileUpload($info);
    			
    		//unlink($physicalFilePath);

    		// Set initial metadata for collection
    		//$this->properties[$path] = $this->initializeProperties( $path, false );

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

        // This automatically creates the property storage
        $storage = $this->getPropertyStoragePath( $path );
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

    	$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['nwan_webdavserver']['natural_file_names'] = true;
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
    		$this->properties[$path] = $this->initializeProperties( $path, false );

    	}
    	else
    	{
    		$physicalFilePath=$virtualFilePath;
    		 
    		file_put_contents( $physicalFilePath, $content );
    		 
    		chmod( $physicalFilePath, 0755 );
    	}
    }

    /**
     * Returns the storage path for a property.
     *
     * Returns the file systems path where properties are stored for the
     * resource identified by $path. This depends on the name of the resource.
     * 
     * @param string $path 
     * @return string
     */
    protected function getPropertyStoragePath( $path )
    {
        if($path == '/') $path .= 'root';
    	
    	// Get storage path for properties depending on the type of the
        // resource.
        $storagePath = realpath( dirname( $this->tokensStorageFile ) ) 
            . DS . $this->propertyStoragePath . DS
            . basename( $path ) . '.xml';

        // Create property storage if it does not exist yet
        if ( !is_dir( dirname( $storagePath ) ) )
        {
            mkdir( dirname( $storagePath ), $this->directoryMode );
        }

        // Append name of namespace to property storage path
        return $storagePath;
    }
    
	/**
     * Returns the property storage for a resource.
     *
     * Returns the {@link ezcWebdavPropertyStorage} instance containing the
     * properties for the resource identified by $path.
     * 
     * @param string $path 
     * @return ezcWebdavBasicPropertyStorage
     */
    protected function getPropertyStorage( $path )
    {
        $storagePath = $this->getPropertyStoragePath( $path );

        // If no properties has been stored yet, just return an empty property
        // storage.
        if ( !is_file( $storagePath ) )
        {
            return new ezcWebdavBasicPropertyStorage();
        }

        // Create handler structure to read properties
        $handler = new ezcWebdavPropertyHandler(
            $xml = new ezcWebdavXmlTool()
        );
        $storage = new ezcWebdavBasicPropertyStorage();

        // Read document
        try
        {
             $doc = $xml->createDom( file_get_contents( $storagePath ) );
        }
        catch ( ezcWebdavInvalidXmlException $e )
        {
            throw new ezcWebdavFileBackendBrokenStorageException(
                "Could not open XML as DOMDocument: '{$storage}'."
            );
        }

        // Get property node from document
        $properties = $doc->getElementsByTagname( 'properties' )->item( 0 )->childNodes;

        // Extract and return properties
        $handler->extractProperties( 
            $properties,
            $storage
        );

        return $storage;
    }

    /**
     * Stores properties for a resource.
     *
     * Creates a new property storage file and stores the properties given for
     * the resource identified by $path.  This depends on the affected resource
     * and the actual properties in the property storage.
     * 
     * @param string $path 
     * @param ezcWebdavBasicPropertyStorage $storage 
     * @return void
     */
    protected function storeProperties( $path, ezcWebdavBasicPropertyStorage $storage )
    {
        $storagePath = $this->getPropertyStoragePath( $path );

        // Create handler structure to read properties
        $handler = new ezcWebdavPropertyHandler(
            $xml = new ezcWebdavXmlTool()
        );

        // Create new dom document with property storage for one namespace
        $doc = new DOMDocument( '1.0' );

        $properties = $doc->createElement( 'properties' );
        $doc->appendChild( $properties );

        // Store and store properties
        $handler->serializeProperties(
            $storage,
            $properties
        );

        return $doc->save( $storagePath );
    }

    /**
     * Manually sets a property on a resource.
     *
     * Sets the given $propertyBackup for the resource identified by $path.
     * 
     * @param string $path 
     * @param ezcWebdavProperty $property
     * @return bool
     */
    public function setProperty( $path, ezcWebdavProperty $property )
    {
        // Check if property is a self handled live property and return an
        // error in this case.
        if ( ( $property->namespace === 'DAV:' ) &&
             in_array( $property->name, $this->handledLiveProperties, true ) &&
             ( $property->name !== 'getcontenttype' ) &&
             ( $property->name !== 'lockdiscovery' ) )
        {
            return false;
        }

        // Get namespace property storage
        $storage = $this->getPropertyStorage( $path );

        // Attach property to store
        $storage->attach( $property );

        // Store document back
        $this->storeProperties( $path, $storage );

        return true;
    }

    /**
     * Manually removes a property from a resource.
     *
     * Removes the given $property form the resource identified by $path.
     * 
     * @param string $path 
     * @param ezcWebdavProperty $property
     * @return bool
     */
    public function removeProperty( $path, ezcWebdavProperty $property )
    {
        // Live properties may not be removed.
        if ( $property instanceof ezcWebdavLiveProperty )
        {
            return false;
        }

        // Get namespace property storage
        $storage = $this->getPropertyStorage( $path );

        // Attach property to store
        $storage->detach( $property->name, $property->namespace );

        // Store document back
        $this->storeProperties( $path, $storage );

        return true;
    }

    /**
     * Resets the property storage for a resource.
     *
     * Discardes the current {@link ezcWebdavPropertyStorage} of the resource
     * identified by $path and replaces it with the given $properties.
     * 
     * @param string $path 
     * @param ezcWebdavPropertyStorage $storage
     * @return bool
     */
    public function resetProperties( $path, ezcWebdavPropertyStorage $storage )
    {
        $this->storeProperties( $path, $storage );
    }

    /**
     * Returns a property of a resource.
     * 
     * Returns the property with the given $propertyName, from the resource
     * identified by $path. You may optionally define a $namespace to receive
     * the property from.
     *
     * @param string $path 
     * @param string $propertyName 
     * @param string $namespace 
     * @return ezcWebdavProperty
     */
    public function getProperty( $path, $propertyName, $namespace = 'DAV:' )
    {
        $storage = $this->getPropertyStorage( $path );

        // Handle dead propreties
        if ( $namespace !== 'DAV:' )
        {
            $properties = $storage->getAllProperties();
            return $properties[$namespace][$propertyName];
        }
        
        // distinguish real and virtual resources
        $t3path = $path;
        $isT3Collection = false;
        $isWebmount = strpos($path, T3_FTPD_WWW_ROOT) ? true : false;
        
        // detect type of virtual resource
        if($isWebmount)
        {
	        $isT3Collection = $this->CFG->t3io->T3IsDir($path);
	        if($isT3Collection)
	        {
	        	$t3path = $this->CFG->t3io->_slashify($path);
	        }
        } 

        // Handle live properties
        switch ( $propertyName )
        {
            case 'getcontentlength':
                $property = new ezcWebdavGetContentLengthProperty();
                $contentlength = $isT3Collection ?
                        ezcWebdavGetContentLengthProperty::COLLECTION :
                        (string) $this->CFG->t3io->T3FileSize($t3path);
                $property->length = $contentlength;
                return $property;

            case 'getlastmodified':
                $property = new ezcWebdavGetLastModifiedProperty();
                $mtime = $isWebmount ?
                	(($mtime = (int) $this->CFG->t3io->T3FileMTime($name)) ? $mtime : '0000000001'):
                	'@' . filemtime( $this->CFG->t3io->T3MakeFilePath($path) );
                $property->date = new ezcWebdavDateTime( $mtime );
                return $property;

            case 'creationdate':
                $property = new ezcWebdavCreationDateProperty();
                $ctime = $isWebmount ?
                	(($ctime = $this->CFG->t3io->T3FileCTime($t3path)) ? $ctime : '0000000001'):
                	'@' . filectime( $this->CFG->t3io->T3MakeFilePath($path) );
                $property->date = new ezcWebdavDateTime( $ctime );
            
                return $property;

            case 'displayname':
                $property = new ezcWebdavDisplayNameProperty();
                $property->displayName = urldecode( basename( $path ) );
                return $property;

            case 'getcontenttype':
            	$mimettype = $isWebmount ?
            		($isT3Collection ? 'httpd/unix-directory' : 'application/octet-stream'):
            		$this->getMimeType( $path );
                $property = new ezcWebdavGetContentTypeProperty($mimettype);
                return $property;

            case 'getetag':
                $property = new ezcWebdavGetEtagProperty();
                $property->etag = $this->getETag( $path );
                return $property;

            case 'resourcetype':
                $property = new ezcWebdavResourceTypeProperty();
                $property->type = $isWebmount ?
                	( 
                		$isT3Collection ? 
	                    	ezcWebdavResourceTypeProperty::TYPE_COLLECTION : 
	                    	ezcWebdavResourceTypeProperty::TYPE_RESOURCE
                    ):
                	(
                		$this->isCollection( $path ) ?
	                    	ezcWebdavResourceTypeProperty::TYPE_COLLECTION : 
	                    	ezcWebdavResourceTypeProperty::TYPE_RESOURCE
					);
                return $property;

            case 'supportedlock':
                $property = new ezcWebdavSupportedLockProperty();
                return $property;

            case 'lockdiscovery':
                $property = new ezcWebdavLockDiscoveryProperty();
                return $property;

            default:
                // Handle all other live properties like dead properties
                $properties = $storage->getAllProperties();
                return $properties[$namespace][$propertyName];
        }
    }

    /**
     * Returns the content length.
     *
     * Returns the content length (filesize) of the resource identified by
     * $path. 
     *
     * @param string $path
     * @return string The content length.
     */
    private function getContentLength( $path )
    {	
//        $length = ezcWebdavGetContentLengthProperty::COLLECTION;
//        if ( !$this->isCollection( $path ) )
//        {
//            $length = (string) filesize( $this->root . $path );
//        }
//        
//        return $length;

    	// distinguish real and virtual resources
        $t3path = $path;
        $isT3Collection = false;
        $isWebmount = strpos($path, T3_FTPD_WWW_ROOT) ? true : false;
        
        // detect type of virtual resource
        if($isWebmount)
        {
	        $isT3Collection = $this->CFG->t3io->T3IsDir($path);
	        if($isT3Collection)
	        {
	        	$t3path = $this->CFG->t3io->_slashify($path);
	        }
        } 
    	$length = $isT3Collection ?
                        ezcWebdavGetContentLengthProperty::COLLECTION :
                        (string) $this->CFG->t3io->T3FileSize($t3path);
        
        return $length;
    }

    /**
     * Returns the etag representing the current state of $path.
     * 
     * Calculates and returns the ETag for the resource represented by $path.
     * The ETag is calculated from the $path itself and the following
     * properties, which are concatenated and md5 hashed:
     *
     * <ul>
     *  <li>getcontentlength</li>
     *  <li>getlastmodified</li>
     * </ul>
     *
     * This method can be overwritten in custom backend implementations to
     * access the information needed directly without using the way around
     * properties.
     *
     * Custom backend implementations are encouraged to use the same mechanism
     * (or this method itself) to determine and generate ETags.
     * 
     * @param mixed $path 
     * @return void
     */
    protected function getETag( $path )
    {
        clearstatcache();

    	// distinguish real and virtual resources
        $t3path = $path;
        $isT3Collection = false;
        $isWebmount = strpos($path, T3_FTPD_WWW_ROOT) ? true : false;
        
        $mtime = $isWebmount ?
                	(($mtime = (int) $this->CFG->t3io->T3FileMTime($name)) ? $mtime : '0000000001'):
                	'@' . filemtime( $this->CFG->t3io->T3MakeFilePath($path) );
        
        return md5(
            $path
            . $this->getContentLength( $path )
            . date( 'c', (int) $mtime )
        );
    }

    /**
     * Returns all properties for a resource.
     * 
     * Returns all properties for the resource identified by $path as a {@link
     * ezcWebdavBasicPropertyStorage}.
     *
     * @param string $path 
     * @return ezcWebdavPropertyStorage
     */
    public function getAllProperties( $path )
    {
        $storage = $this->getPropertyStorage( $path );
        
        // Add all live properties to stored properties
        foreach ( $this->handledLiveProperties as $property )
        {
            $storage->attach(
                $this->getProperty( $path, $property )
            );
        }

        return $storage;
    }

    /**
     * Copy resources recursively from one path to another.
     *
     * Returns an array with {@link ezcWebdavErrorResponse}s for all subtree,
     * where the copy operation failed. Errors subsequent nodes in a subtree
     * should be ommitted.
     *
     * If an empty array is return, the operation has been completed
     * successfully.
     * 
     * @param string $fromPath 
     * @param string $toPath 
     * @param int $depth
     * @return array(ezcWebdavErrorResponse)
     */
    protected function performCopy( $fromPath, $toPath, $depth = ezcWebdavRequest::DEPTH_INFINITY )
    {
//        $causeErrors = (bool) ( $this->options->failingOperations & ( ezcWebdavMemoryBackendOptions::REQUEST_COPY | ezcWebdavMemoryBackendOptions::REQUEST_MOVE ) );
//        $errors = array();
//        
//        if ( !is_array( $this->content[$fromPath] ) ||
//             ( is_array( $this->content[$fromPath] ) && ( $depth === ezcWebdavRequest::DEPTH_ZERO ) ) )
//        {
//            // Copy a resource, or a collection, but the depth header told not
//            // to recurse into collections
//            if ( $causeErrors && preg_match( $this->options->failForRegexp, $fromPath ) > 0 )
//            {
//                // Completely abort with error
//                return array( ezcWebdavErrorResponse(
//                    ezcWebdavResponse::STATUS_423,
//                    $fromPath
//                ) );
//            }
//            if ( $causeErrors && preg_match( $this->options->failForRegexp, $toPath ) > 0 )
//            {
//                // Completely abort with error
//                return array( ezcWebdavErrorResponse(
//                    ezcWebdavResponse::STATUS_412,
//                    $toPath
//                ) );
//            }
//
//            // Perform copy operation
//            if ( is_array( $this->content[$fromPath] ) )
//            {
//                // Create a new empty collection
//                $this->content[$toPath] = array();
//            }
//            else
//            {
//                // Copy file content
//                $this->content[$toPath] = $this->content[$fromPath];
//            }
//
//            // Copy properties
//            $this->cloneProperties(
//                $toPath,
//                is_array( $this->content[$toPath] ),
//                $this->props[$fromPath]
//            );
//
//            // Add to parent node
//            $this->content[dirname( $toPath )][] = $toPath;
//        }
//        else
//        {
//            // Copy a collection
//            $errnousSubtrees = array();
//
//            // Array of copied collections, where the child names are required
//            // to be modified depending on the success of the copy operation.
//            $copiedCollections = array();
//
//            // Check all nodes, if they math the fromPath
//            foreach ( $this->content as $resource => $content )
//            {
//                if ( strpos( $resource, $fromPath ) !== 0 )
//                {
//                    // This resource is not affected by the copy operation
//                    continue;
//                }
//
//                // Check if this resource should be skipped, because
//                // already one of the parent nodes caused an error.
//                foreach ( $errnousSubtrees as $subtree )
//                {
//                    if ( strpos( $resource, $subtree ) )
//                    {
//                        // Skip resource, then.
//                        continue 2;
//                    }
//                }
//
//                // Check if this resource should cause an error
//                if ( $causeErrors && preg_match( $this->options->failForRegexp, $resource ) )
//                {
//                    // Cause an error and skip resource
//                    $errors[] = new ezcWebdavErrorResponse(
//                        ezcWebdavResponse::STATUS_423,
//                        $resource
//                    );
//                    continue;
//                }
//
//                // To actually perform the copy operation, modify the
//                // destination resource name
//                $newResourceName = preg_replace( '(^' . preg_quote( $fromPath ) . ')', $toPath, $resource );
//                
//                // Check if this resource should cause an error
//                if ( $causeErrors && preg_match( $this->options->failForRegexp, $newResourceName ) )
//                {
//                    // Cause an error and skip resource
//                    $errors[] = new ezcWebdavErrorResponse(
//                        ezcWebdavResponse::STATUS_412,
//                        $newResourceName
//                    );
//                    continue;
//                }
//                
//                // Add collection to collection child recalculation array
//                if ( is_array( $this->content[$resource] ) )
//                {
//                    $copiedCollections[] = $newResourceName;
//                }
//
//                // Actually copy
//                $this->content[$newResourceName] = $this->content[$resource];
//
//                // Copy properties
//                $this->cloneProperties(
//                    $newResourceName,
//                    is_array( $this->content[$resource] ),
//                    $this->props[$resource]
//                );
//
//                // Add to parent node
//                $this->content[dirname( $newResourceName )][] = $newResourceName;
//            }
//
//            // Iterate over all copied collections and update the child
//            // references
//            foreach ( $copiedCollections as $collection )
//            {
//                foreach ( $this->content[$collection] as $nr => $child )
//                {
//                    foreach ( $errnousSubtrees as $subtree )
//                    {
//                        if ( strpos( $child, $subtree ) )
//                        {
//                            // If child caused an error, it has not been
//                            // copied, so we remove it.
//                            unset( $this->content[$collection][$nr] );
//                            continue 2;
//                        }
//                    }
//
//                    // Also remove all references to old children, new children
//                    // have already been added during the last step.
//                    if ( preg_match( '(^' . preg_quote( $fromPath ) . ')', $child ) )
//                    {
//                        unset( $this->content[$collection][$nr] );
//                    }
//                }
//
//                $this->content[$collection] = array_values( $this->content[$collection] );
//            }
//        }
//
//        return $errors;
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
    	return $this->CFG->t3io->T3FileExists($path);
    }

    /**
     * Returns if resource is a collection.
     *
     * Returns if the resource identified by $path is a collection resource
     * (true) or a non-collection one (false).
     * 
     * @param string $path 
     * @return bool
     */
    protected function isCollection( $path )
    {
    	// distinguish real and virtual resources
        $isCollection = false;
        $isWebmount = strpos($path, T3_FTPD_WWW_ROOT) ? true : false;
        
        // detect type of virtual resource
        if($isWebmount)
        {
	        $isCollection = $this->CFG->t3io->T3IsDir($path);
        }
        else
        {
        	$isCollection = is_dir( $this->CFG->t3io->T3MakeFilePath($path) );	
        } 
        
        return $isCollection;
    }

    /**
     * Get members of collection.
     *
     * Returns an array with the members of the collection given by the path of
     * the collection.
     *
     * The returned array holds elements which are either ezcWebdavCollection,
     * or ezcWebdavResource.
     * 
     * @param string $path 
     * @return array
     */
    protected function getCollectionMembers( $path )
    {
        $contents = array();

        foreach ( $this->content[$path] as $child )
        {
            if ( is_array( $this->content[$child] ) )
            {
                // Add collection without any children
                $contents[] = new ezcWebdavCollection(
                    $child
                );
            }
            else
            {
                // Add files without content
                $contents[] = new ezcWebdavResource(
                    $child
                );
            }
        }

        return $contents;
    }
    
//	/**
//     * Wait and get lock for complete directory tree.
//     *
//     * Acquire lock for the complete tree for read or write operations. This
//     * does not implement any priorities for operations, or check if several
//     * read operation may run in parallel. The plain locking should / could be
//     * extended by something more sophisticated.
//     *
//     * If the tree already has been locked, the method waits until the lock can
//     * be acquired.
//     *
//     * The optional second parameter $readOnly indicates wheather a read only
//     * lock should be acquired. This may be used by extended implementations,
//     * but it is not used in this implementation.
//     *
//     * @param bool $readOnly
//     * @return void
//     *
//     * @todo The locking mechanism affects the ETag of the base collection. The
//     *       ETag is different on each request, which might result in problems
//     *       for clients that make extensive use of If-* headers. No client is
//     *       known so far, if problems occur here we need to find a solution
//     *       for this.
//     */
//    protected function acquireLock( $readOnly = false )
//    {
//        if ( $this->noLock )
//        {
//            return true;
//        }
//        
//        try
//        {
//            $this->lock( $this->waitForLock, $this->lockTimeout );
//        }
//        catch ( ezcWebdavLockTimeoutException $e )
//        {
//            return false;
//        }
//        return true;
//    }
//
//    /**
//     * Free lock.
//     *
//     * Frees the lock after the operation has been finished.
//     * 
//     * @return void
//     */
//    protected function freeLock()
//    {
//        if ( $this->noLock )
//        {
//            return true;
//        }
//        
//        $this->unlock();
//    }
//
//    /**
//     * Serves GET requests.
//     *
//     * The method receives a {@link ezcWebdavGetRequest} object containing all
//     * relevant information obout the clients request and will return an {@link
//     * ezcWebdavErrorResponse} instance on error or an instance of {@link
//     * ezcWebdavGetResourceResponse} or {@link ezcWebdavGetCollectionResponse}
//     * on success, depending on the type of resource that is referenced by the
//     * request.
//     *
//     * This method acquires the internal lock of the backend, dispatches to
//     * {@link ezcWebdavSimpleBackend} to perform the operation and releases the
//     * lock afterwards.
//     *
//     * @param ezcWebdavGetRequest $request
//     * @return ezcWebdavResponse
//     */
//    public function get( ezcWebdavGetRequest $request )
//    {
//        $this->acquireLock( true );
//        $return = parent::get( $request );
//        $this->freeLock();
//
//        return $return;
//    }
//
//    /**
//     * Serves HEAD requests.
//     *
//     * The method receives a {@link ezcWebdavHeadRequest} object containing all
//     * relevant information obout the clients request and will return an {@link
//     * ezcWebdavErrorResponse} instance on error or an instance of {@link
//     * ezcWebdavHeadResponse} on success.
//     *
//     * This method acquires the internal lock of the backend, dispatches to
//     * {@link ezcWebdavSimpleBackend} to perform the operation and releases the
//     * lock afterwards.
//     * 
//     * @param ezcWebdavHeadRequest $request
//     * @return ezcWebdavResponse
//     */
//    public function head( ezcWebdavHeadRequest $request )
//    {
//        $this->acquireLock( true );
//        $return = parent::head( $request );
//        $this->freeLock();
//
//        return $return;
//    }
//
//    /**
//     * Serves PROPFIND requests.
//     * 
//     * The method receives a {@link ezcWebdavPropFindRequest} object containing
//     * all relevant information obout the clients request and will either
//     * return an instance of {@link ezcWebdavErrorResponse} to indicate an error
//     * or a {@link ezcWebdavPropFindResponse} on success. If the referenced
//     * resource is a collection or if some properties produced errors, an
//     * instance of {@link ezcWebdavMultistatusResponse} may be returned.
//     *
//     * The {@link ezcWebdavPropFindRequest} object contains a definition to
//     * find one or more properties of a given collection or non-collection
//     * resource.
//     *
//     * This method acquires the internal lock of the backend, dispatches to
//     * {@link ezcWebdavSimpleBackend} to perform the operation and releases the
//     * lock afterwards.
//     *
//     * @param ezcWebdavPropFindRequest $request
//     * @return ezcWebdavResponse
//     */
//    public function propFind( ezcWebdavPropFindRequest $request )
//    {
//        $this->acquireLock( true );
//        $return = parent::propFind( $request );
//        $this->freeLock();
//
//        return $return;
//    }
//
//    /**
//     * Serves PROPPATCH requests.
//     * 
//     * The method receives a {@link ezcWebdavPropPatchRequest} object
//     * containing all relevant information obout the clients request and will
//     * return an instance of {@link ezcWebdavErrorResponse} on error or a
//     * {@link ezcWebdavPropPatchResponse} response on success. If the
//     * referenced resource is a collection or if only some properties produced
//     * errors, an instance of {@link ezcWebdavMultistatusResponse} may be
//     * returned.
//     *
//     * This method acquires the internal lock of the backend, dispatches to
//     * {@link ezcWebdavSimpleBackend} to perform the operation and releases the
//     * lock afterwards.
//     *
//     * @param ezcWebdavPropPatchRequest $request
//     * @return ezcWebdavResponse
//     */
//    public function propPatch( ezcWebdavPropPatchRequest $request )
//    {
//        $this->acquireLock();
//        $return = parent::propPatch( $request );
//        $this->freeLock();
//
//        return $return;
//    }
//
//    /**
//     * Serves PUT requests.
//     *
//     * The method receives a {@link ezcWebdavPutRequest} objects containing all
//     * relevant information obout the clients request and will return an
//     * instance of {@link ezcWebdavErrorResponse} on error or {@link
//     * ezcWebdavPutResponse} on success.
//     * 
//     * This method acquires the internal lock of the backend, dispatches to
//     * {@link ezcWebdavSimpleBackend} to perform the operation and releases the
//     * lock afterwards.
//     *
//     * @param ezcWebdavPutRequest $request 
//     * @return ezcWebdavResponse
//     */
//    public function put( ezcWebdavPutRequest $request )
//    {
//        $this->acquireLock();
//        $return = parent::put( $request );
//        $this->freeLock();
//
//        return $return;
//    }
//
//    /**
//     * Serves DELETE requests.
//     *
//     * The method receives a {@link ezcWebdavDeleteRequest} objects containing
//     * all relevant information obout the clients request and will return an
//     * instance of {@link ezcWebdavErrorResponse} on error or {@link
//     * ezcWebdavDeleteResponse} on success.
//     *
//     * This method acquires the internal lock of the backend, dispatches to
//     * {@link ezcWebdavSimpleBackend} to perform the operation and releases the
//     * lock afterwards.
//     * 
//     * @param ezcWebdavDeleteRequest $request 
//     * @return ezcWebdavResponse
//     */
//    public function delete( ezcWebdavDeleteRequest $request )
//    {
//        $this->acquireLock();
//        $return = parent::delete( $request );
//        $this->freeLock();
//
//        return $return;
//    }
//
//    /**
//     * Serves COPY requests.
//     *
//     * The method receives a {@link ezcWebdavCopyRequest} objects containing
//     * all relevant information obout the clients request and will return an
//     * instance of {@link ezcWebdavErrorResponse} on error or {@link
//     * ezcWebdavCopyResponse} on success. If only some operations failed, this
//     * method may return an instance of {@link ezcWebdavMultistatusResponse}.
//     *
//     * This method acquires the internal lock of the backend, dispatches to
//     * {@link ezcWebdavSimpleBackend} to perform the operation and releases the
//     * lock afterwards.
//     * 
//     * @param ezcWebdavCopyRequest $request 
//     * @return ezcWebdavResponse
//     */
//    public function copy( ezcWebdavCopyRequest $request )
//    {
//        $this->acquireLock();
//        $return = parent::copy( $request );
//        $this->freeLock();
//
//        return $return;
//    }
//
//    /**
//     * Serves MOVE requests.
//     *
//     * The method receives a {@link ezcWebdavMoveRequest} objects containing
//     * all relevant information obout the clients request and will return an
//     * instance of {@link ezcWebdavErrorResponse} on error or {@link
//     * ezcWebdavMoveResponse} on success. If only some operations failed, this
//     * method may return an instance of {@link ezcWebdavMultistatusResponse}.
//     *
//     * This method acquires the internal lock of the backend, dispatches to
//     * {@link ezcWebdavSimpleBackend} to perform the operation and releases the
//     * lock afterwards.
//     * 
//     * @param ezcWebdavMoveRequest $request 
//     * @return ezcWebdavResponse
//     */
//    public function move( ezcWebdavMoveRequest $request )
//    {
//        $this->acquireLock();
//        $return = parent::move( $request );
//        $this->freeLock();
//
//        return $return;
//    }
//
//    /**
//     * Serves MKCOL (make collection) requests.
//     *
//     * The method receives a {@link ezcWebdavMakeCollectionRequest} objects
//     * containing all relevant information obout the clients request and will
//     * return an instance of {@link ezcWebdavErrorResponse} on error or {@link
//     * ezcWebdavMakeCollectionResponse} on success.
//     *
//     * This method acquires the internal lock of the backend, dispatches to
//     * {@link ezcWebdavSimpleBackend} to perform the operation and releases the
//     * lock afterwards.
//     * 
//     * @param ezcWebdavMakeCollectionRequest $request 
//     * @return ezcWebdavResponse
//     */
//    public function makeCollection( ezcWebdavMakeCollectionRequest $request )
//    {
//        $this->acquireLock();
//        $return = parent::makeCollection( $request );
//        $this->freeLock();
//
//        return $return;
//    }
    
    /**
     * Read valid data from given content array and initialize property
     * storage.
     * 
     * @param array $contents 
     * @param string $path
     * @return void
     */
    public function addContents( array $contents, $path = '/' )
    {
        foreach ( $contents as $name => $content )
        {
            if ( !is_string( $name ) )
            {
                // Ignore elements which do not have a string key
                continue;
            }

            // Full path to resource
            $resourcePath = $path . $name;

            if ( is_array( $content ) )
            {
                // Content is a collection
                $this->content[$resourcePath] = array();
                $this->properties[$resourcePath] = $this->initializeProperties(
                    $resourcePath,
                    true
                );

                // Recurse
                $this->addContents( $content, $resourcePath . '/' );
            }
            elseif ( is_string( $content ) )
            {
                // Content is a file
                $this->content[$resourcePath] = $content;
                $this->properties[$resourcePath] = $this->initializeProperties(
                    $resourcePath
                );
            }
            else
            {
                // Ignore everything else...
                continue;
            }

            // Add contents to parent directory
            $parent = ( $path === '/' ? '/' : substr( $path, 0, -1 ) );
            $this->content[$parent][] = $resourcePath;
        }
    }
}