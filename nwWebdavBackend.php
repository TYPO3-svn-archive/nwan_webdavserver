<?php
class nwWebdavBackend 
extends ezcWebdavSimpleBackend 
implements ezcWebdavLockBackend 
{
	/**
     * Mimetype for directories.
     */
    const DIRECTORY_MIMETYPE = 'httpd/unix-directory';

    /**
     * Mimetype for eZ Publish objects which don't have a mimetype.
     */
    const DEFAULT_MIMETYPE = "application/octet-stream";

    /**
     * Default size in bytes for eZ Publish objects which don't have a size.
     */
    const DEFAULT_SIZE = 0;
    
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
     * Holds the retrieved nodes to allow for faster retrieval on subsequent requests.
     *
     * @var array(string=>array())
     */
    protected $cachedNodes = array();

    /**
     * Holds the retrieved properties to allow for faster retrieval on subsequent requests.
     *
     * @var array(string=>array())
     */
    protected $cachedProperties = array();

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
		'encode_t3CurrDirNodes' => true,
		'allowFiles'			=> array(),
		'denyFiles'				=> array('/\.*','\.*'),
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
			case 'encode_t3CurrDirNodes':
			case 'allowFiles':
			case 'denyFiles':
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
    	$this->CFG->t3io->metaftpd_devlog(10,"( $path )",__METHOD__, get_defined_vars() );
    }

    /**
     * Removes the lock.
     * 
     * @return void
     */
    public function unlock()
    {
        $this->CFG->t3io->metaftpd_devlog(10,"( $path )",__METHOD__, get_defined_vars() );
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
        $this->CFG->t3io->metaftpd_devlog(10,"( $path )",__METHOD__, get_defined_vars() );
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
        $this->CFG->t3io->metaftpd_devlog(10,"( $path )",__METHOD__, get_defined_vars() );
    }
    
    /**
     * Create a new collection.
     *
     * Creates a new collection at the given $path.
     * 
     * @param string $path 
     * @return bool
     */
    protected function createCollection( $path )
    {
    	$t3io=$this->CFG->t3io;
    	$path=$t3io->T3CleanFilePath($path);
    	
    	$parent = $this->splitLastPathElement( $path, $name );
    	
		$info=$t3io->T3IsFile($path);
    	
    	$t3io->metaftpd_devlog(300,"( $path )",__METHOD__, get_defined_vars() );
    	
    	if ($info['isWebmount']) 
		{	
			$parent = dirname($path);
			$name   = basename($path);

			$page['pid']=$info['pid'];
			$page['title']=$name;
			$this->CFG->T3DB->exec_INSERTquery('pages',$page);
			$t3io->T3ClearPageCache($page['pid']);
			$t3io->T3ClearAllCache();
			
			$storage = $this->getPropertyStoragePath( $path . '/foo' );
			
			$t3io->metaftpd_devlog(300,"( $path )",__METHOD__, get_defined_vars() );
			
			return true;
		}
		else // path points into FILEMOUNT
		{
			$path=$t3io->T3MakeFilePath($path);
			$path=$t3io->T3CleanFilePath($path);
			
			$parent = dirname($path);
			$name   = basename($path);
			
			if($stat = mkdir($parent."/".$name, 0777))
			{
				$storage = $this->getPropertyStoragePath( $path . '/foo' );
				
				$t3io->metaftpd_devlog(300,"( $path )",__METHOD__, get_defined_vars() );
				
				return true;
			}
			
		}
		
    	return false;
    	
    }

    /**
     * Create a new resource.
     *
     * Creates a new resource at the given $path, optionally with the given
     * $content.
     * 
     * @param string $path 
     * @param string $content 
     * @return void
     */
    protected function createResource( $path, $content = null )
    {
    	$this->CFG->t3io->metaftpd_devlog(10,"( $path )",__METHOD__, get_defined_vars() );
    }

    /**
     * Changes contents of a resource.
     *
     * This method is used to change the contents of the resource identified by
     * $path to the given $content.
     * 
     * @param string $path 
     * @param string $content 
     * @return void
     */
    protected function setResourceContents( $path, $content )
    {
    	$this->CFG->t3io->metaftpd_devlog(10,"( $path )",__METHOD__, get_defined_vars() );
    }

    /**
     * Returns the content of a resource.
     *
     * Returns the content of the resource identified by $path.
     * 
     * @param string $path 
     * @return string
     */
    protected function getResourceContents( $path )
    {
    	$this->CFG->t3io->metaftpd_devlog(10,"( $path )",__METHOD__, get_defined_vars() );
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
        if ( !in_array( $property->name, $this->handledLiveProperties, true ) )
        {
            return false;
        }

        // @as @todo implement setting properties
        // @todo implement locking and unlocking based on the code
        // lock:
        // replace 30607 with your object ID
        // $object = eZContentObject::fetch( 30607 );
        // $stateGroup = eZContentObjectStateGroup::fetchByIdentifier( 'ez_lock' );
        // $state = eZContentObjectState::fetchByIdentifier( 'locked', $stateGroup->attribute( 'id' ) );
        // $object->assignState( $state );

        // unlock:
        // $state = eZContentObjectState::fetchByIdentifier( 'not_locked', $stateGroup->attribute( 'id' ) );
        // $object->assignState( $state );

        // Get namespace property storage
        $storage = new ezcWebdavBasicPropertyStorage();

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
        // @as @todo implement removing properties
        return true;
    }

    /**
     * Resets the property storage for a resource.
     *
     * Discards the current {@link ezcWebdavPropertyStorage} of the resource
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
    	$this->CFG->t3io->metaftpd_devlog(30,"( $path, $propertyName )",__METHOD__, get_defined_vars() );
    	
    	$storage = $this->getPropertyStorage( $path );

        // Handle dead propreties
        if ( $namespace !== 'DAV:' )
        {
            $properties = $storage->getAllProperties();
            return $properties[$namespace][$name];
        }

        if ( !isset( $this->cachedProperties[$path] ) )
        {
            	// TODO: fetch dead properties only
        	$this->cachedProperties[$path] = $this->getNodeInfo( $path );
        }

        $item = $this->cachedProperties[$path];

        // Handle live properties
        switch ( $propertyName )
        {
            case 'getcontentlength':
                $property = new ezcWebdavGetContentLengthProperty();
                $mimetype = isset( $item['mimetype'] ) ? 
                	$item['mimetype']: 
                	self::DEFAULT_MIMETYPE;
                $size = isset( $item['size'] ) ? 
                	$item['size']: 
                	self::DEFAULT_SIZE;
                $property->length = ( $mimetype === self::DIRECTORY_MIMETYPE ) ?
                    ezcWebdavGetContentLengthProperty::COLLECTION :
                    (string) $size;
                break;

            case 'getlastmodified':
                $property = new ezcWebdavGetLastModifiedProperty();
                $timestamp = isset( $item['mtime'] ) ? 
                	$item['mtime']: 
                	time();
                $property->date = new ezcWebdavDateTime( '@' .$timestamp );
                break;

            case 'creationdate':
                $property = new ezcWebdavCreationDateProperty();
                $timestamp = isset( $item['ctime'] ) ? 
                	$item['ctime']: 
                	time();
                $property->date = new ezcWebdavDateTime( '@' . $timestamp );
                break;

            case 'displayname':
                $property = new ezcWebdavDisplayNameProperty();
                $property->displayName = isset( $item['name'] ) ? 
                	$item['name']: 
                	'Unknown displayname';
                break;

            case 'getcontenttype':
                $property = new ezcWebdavGetContentTypeProperty();
                $property->mime = isset( $item['mimetype'] ) ? 
                	$item['mimetype']: 
                	self::DEFAULT_MIMETYPE;
                break;

            case 'getetag':
                $property = new ezcWebdavGetEtagProperty();
                $mimetype = isset( $item['mimetype'] ) ? 
                	$item['mimetype']: 
                	self::DEFAULT_MIMETYPE;
                $size = isset( $item['size'] ) ? 
                	$item['size']: 
                	self::DEFAULT_SIZE;
                $size = ( $mimetype === self::DIRECTORY_MIMETYPE ) ?
                    ezcWebdavGetContentLengthProperty::COLLECTION:
                    (string) $size;
                $timestamp = isset( $item['mtime'] ) ? 
                	$item['mtime']: 
                	time();
                $property->etag = md5( $path . $size . date( 'c', $timestamp ) );
                break;

            case 'resourcetype':
                $property = new ezcWebdavResourceTypeProperty();
                $mimetype = isset( $item['mimetype'] ) ? 
                	$item['mimetype']: 
                	self::DEFAULT_MIMETYPE;
                $property->type = ( $mimetype === self::DIRECTORY_MIMETYPE ) ?
                    ezcWebdavResourceTypeProperty::TYPE_COLLECTION:
                    ezcWebdavResourceTypeProperty::TYPE_RESOURCE;
                break;

            case 'supportedlock':
                $property = new ezcWebdavSupportedLockProperty();
                break;

            case 'lockdiscovery':
                $property = new ezcWebdavLockDiscoveryProperty();
                break;

            default:
                // Handle all other live properties like dead properties
                $properties = $storage->getAllProperties();
                $property = $properties['DAV:'][$name]; // @as (need to figure $namespace)
                break;
        }

        return $property;
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
    	$this->CFG->t3io->metaftpd_devlog(10,"( $path )",__METHOD__, get_defined_vars() );
    	
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
     * Copies resources recursively from one path to another.
     *
     * Copies the resourced identified by $fromPath recursively to $toPath with
     * the given $depth, where $depth is one of {@link
     * ezcWebdavRequest::DEPTH_ZERO}, {@link ezcWebdavRequest::DEPTH_ONE},
     * {@link ezcWebdavRequest::DEPTH_INFINITY}.
     *
     * Returns an array with {@link ezcWebdavErrorResponse}s for all subtrees,
     * where the copy operation failed. Errors for subsequent resources in a
     * subtree should be ommitted.
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
    	$this->CFG->t3io->metaftpd_devlog(10,"( $path )",__METHOD__, get_defined_vars() );
    }

    /**
     * Deletes everything below a path.
     *
     * Deletes the resource identified by $path recursively. Returns an
     * instance of {@link ezcWebdavMultistatusResponse} if the deletion failed,
     * and null on success.
     * 
     * @param string $path 
     * @return ezcWebdavMultitstatusResponse|null
     */
    protected function performDelete( $path )
    {
    	$this->CFG->t3io->metaftpd_devlog(10,"( $path )",__METHOD__, get_defined_vars() );
    }

    /**
     * Returns if a resource exists.
     *
     * Returns if a the resource identified by $path exists.
     * 
     * @param string $path 
     * @return bool
     */
    protected function nodeExists( $path )
    {
    	$this->CFG->t3io->metaftpd_devlog(1000,"( $path )",__METHOD__, get_defined_vars() );
    	
    	if ( !isset( $this->cachedNodes[$path] ) )
        {
            $this->cachedNodes[$path] = $this->getNodeInfo( $path );
        }
    	
    	return $this->cachedNodes[$path]['nodeExists'];
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
    	$this->CFG->t3io->metaftpd_devlog(10,"( $path )",__METHOD__, get_defined_vars() );
    	
    	if ( !isset( $this->cachedNodes[$path] ) )
        {
            $this->cachedNodes[$path] = $this->getNodeInfo( $path );
        }

        return $this->cachedNodes[$path]['isCollection'];
    }
    
/**
     * Serves GET requests.
     *
     * The method receives a {@link ezcWebdavGetRequest} object containing all
     * relevant information obout the clients request and will return an {@link
     * ezcWebdavErrorResponse} instance on error or an instance of {@link
     * ezcWebdavGetResourceResponse} or {@link ezcWebdavGetCollectionResponse}
     * on success, depending on the type of resource that is referenced by the
     * request.
     *
     * This method acquires the internal lock of the backend, dispatches to
     * {@link ezcWebdavSimpleBackend} to perform the operation and releases the
     * lock afterwards.
     *
     * @param ezcWebdavGetRequest $request
     * @return ezcWebdavResponse
     */
    public function get( ezcWebdavGetRequest $request )
    {
        $this->acquireLock( true );
        $return = parent::get( $request );
        $this->freeLock();

        return $return;
    }

    /**
     * Serves HEAD requests.
     *
     * The method receives a {@link ezcWebdavHeadRequest} object containing all
     * relevant information obout the clients request and will return an {@link
     * ezcWebdavErrorResponse} instance on error or an instance of {@link
     * ezcWebdavHeadResponse} on success.
     *
     * This method acquires the internal lock of the backend, dispatches to
     * {@link ezcWebdavSimpleBackend} to perform the operation and releases the
     * lock afterwards.
     *
     * @param ezcWebdavHeadRequest $request
     * @return ezcWebdavResponse
     */
    public function head( ezcWebdavHeadRequest $request )
    {
        $this->acquireLock( true );
        $return = parent::head( $request );
        $this->freeLock();

        return $return;
    }
    
/**
     * Serves PROPFIND requests.
     * 
     * The method receives a {@link ezcWebdavPropFindRequest} object containing
     * all relevant information obout the clients request and will either
     * return an instance of {@link ezcWebdavErrorResponse} to indicate an error
     * or a {@link ezcWebdavPropFindResponse} on success. If the referenced
     * resource is a collection or if some properties produced errors, an
     * instance of {@link ezcWebdavMultistatusResponse} may be returned.
     *
     * The {@link ezcWebdavPropFindRequest} object contains a definition to
     * find one or more properties of a given collection or non-collection
     * resource.
     *
     * This method acquires the internal lock of the backend, dispatches to
     * {@link ezcWebdavSimpleBackend} to perform the operation and releases the
     * lock afterwards.
     *
     * @param ezcWebdavPropFindRequest $request
     * @return ezcWebdavResponse
     */
    public function propFind( ezcWebdavPropFindRequest $request )
    {
        $this->acquireLock( true );
        $return = parent::propFind( $request );
        $this->freeLock();

        return $return;
    }

    /**
     * Serves PROPPATCH requests.
     * 
     * The method receives a {@link ezcWebdavPropPatchRequest} object
     * containing all relevant information obout the clients request and will
     * return an instance of {@link ezcWebdavErrorResponse} on error or a
     * {@link ezcWebdavPropPatchResponse} response on success. If the
     * referenced resource is a collection or if only some properties produced
     * errors, an instance of {@link ezcWebdavMultistatusResponse} may be
     * returned.
     *
     * This method acquires the internal lock of the backend, dispatches to
     * {@link ezcWebdavSimpleBackend} to perform the operation and releases the
     * lock afterwards.
     *
     * @param ezcWebdavPropPatchRequest $request
     * @return ezcWebdavResponse
     */
    public function propPatch( ezcWebdavPropPatchRequest $request )
    {
        $this->acquireLock();
        $return = parent::propPatch( $request );
        $this->freeLock();

        return $return;
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
     */
    protected function storeProperties( $path, ezcWebdavBasicPropertyStorage $storage )
    {
        // @as @todo implement storing properties
        return true;
    }

    /**
     * Serves PUT requests.
     *
     * The method receives a {@link ezcWebdavPutRequest} objects containing all
     * relevant information obout the clients request and will return an
     * instance of {@link ezcWebdavErrorResponse} on error or {@link
     * ezcWebdavPutResponse} on success.
     * 
     * This method acquires the internal lock of the backend, dispatches to
     * {@link ezcWebdavSimpleBackend} to perform the operation and releases the
     * lock afterwards.
     *
     * @param ezcWebdavPutRequest $request 
     * @return ezcWebdavResponse
     */
    public function put( ezcWebdavPutRequest $request )
    {
        $this->acquireLock();
        $return = parent::put( $request );
        $this->freeLock();

        return $return;
    }

    /**
     * Serves DELETE requests.
     *
     * The method receives a {@link ezcWebdavDeleteRequest} objects containing
     * all relevant information obout the clients request and will return an
     * instance of {@link ezcWebdavErrorResponse} on error or {@link
     * ezcWebdavDeleteResponse} on success.
     *
     * This method acquires the internal lock of the backend, dispatches to
     * {@link ezcWebdavSimpleBackend} to perform the operation and releases the
     * lock afterwards.
     * 
     * @param ezcWebdavDeleteRequest $request 
     * @return ezcWebdavResponse
     */
    public function delete( ezcWebdavDeleteRequest $request )
    {
        $this->acquireLock();
        $return = parent::delete( $request );
        $this->freeLock();

        return $return;
    }

    /**
     * Serves COPY requests.
     *
     * The method receives a {@link ezcWebdavCopyRequest} objects containing
     * all relevant information obout the clients request and will return an
     * instance of {@link ezcWebdavErrorResponse} on error or {@link
     * ezcWebdavCopyResponse} on success. If only some operations failed, this
     * method may return an instance of {@link ezcWebdavMultistatusResponse}.
     *
     * This method acquires the internal lock of the backend, dispatches to
     * {@link ezcWebdavSimpleBackend} to perform the operation and releases the
     * lock afterwards.
     * 
     * @param ezcWebdavCopyRequest $request 
     * @return ezcWebdavResponse
     */
    public function copy( ezcWebdavCopyRequest $request )
    {
        $this->acquireLock();
        $return = parent::copy( $request );
        $this->freeLock();

        return $return;
    }

    /**
     * Serves MOVE requests.
     *
     * The method receives a {@link ezcWebdavMoveRequest} objects containing
     * all relevant information obout the clients request and will return an
     * instance of {@link ezcWebdavErrorResponse} on error or {@link
     * ezcWebdavMoveResponse} on success. If only some operations failed, this
     * method may return an instance of {@link ezcWebdavMultistatusResponse}.
     *
     * This method acquires the internal lock of the backend, dispatches to
     * {@link ezcWebdavSimpleBackend} to perform the operation and releases the
     * lock afterwards.
     * 
     * @param ezcWebdavMoveRequest $request 
     * @return ezcWebdavResponse
     */
    public function move( ezcWebdavMoveRequest $request )
    {
        $this->acquireLock();
        $return = parent::move( $request );
        $this->freeLock();

        return $return;
    }

    /**
     * Serves MKCOL (make collection) requests.
     *
     * The method receives a {@link ezcWebdavMakeCollectionRequest} objects
     * containing all relevant information obout the clients request and will
     * return an instance of {@link ezcWebdavErrorResponse} on error or {@link
     * ezcWebdavMakeCollectionResponse} on success.
     *
     * This method acquires the internal lock of the backend, dispatches to
     * {@link ezcWebdavSimpleBackend} to perform the operation and releases the
     * lock afterwards.
     * 
     * @param ezcWebdavMakeCollectionRequest $request 
     * @return ezcWebdavResponse
     */
    public function makeCollection( ezcWebdavMakeCollectionRequest $request )
    {
    	$this->CFG->t3io->metaftpd_devlog(300,"( ezcWebdavMakeCollectionRequest )",__METHOD__, $_SERVER );
    	
        $this->acquireLock();
        $return = parent::makeCollection( $request );
        $this->freeLock();

        return $return;
    }

    /**
     * Returns members of collection.
     *
     * Returns an array with the members of the collection identified by $path.
     * The returned array can contain {@link ezcWebdavCollection}, and {@link
     * ezcWebdavResource} instances and might also be empty, if the collection
     * has no members.
     * 
     * @param string $path 
     * @return array(ezcWebdavResource|ezcWebdavCollection)
     */
    protected function getCollectionMembers( $path )
    {
       $this->CFG->t3io->metaftpd_devlog(10,"( $path )",__METHOD__, get_defined_vars() );
        
    	$properties = $this->handledLiveProperties;
        $fullPath = $path;
        $collection = $this->splitFirstPathElement( $path, $currentRootFolder );
        $contents = array();

        if ( !$currentRootFolder )
        {
            $this->CFG->t3io->metaftpd_devlog(9,'!$currentRootFolder',__METHOD__, get_defined_vars() );
        	$entries = $this->fetchVirtualRootFolders( $fullPath, $currentSite, $depth, $properties );
        }
        else if( in_array( $currentRootFolder, array( T3_FTPD_FILE_ROOT, T3_FTPD_WWW_ROOT ) ) )
        {
            $this->CFG->t3io->metaftpd_devlog(9,'in_array( $currentRootFolder...)',__METHOD__, get_defined_vars() );
        	$entries = $this->getVirtualFolderCollection( $currentRootFolder, $collection, $fullPath, $depth, $properties );
        }
        
        $level = count(explode(DS, $path))-2;
        for($i = 1; $i <= $level; $i++){
        	array_shift($entries);
        }

        foreach ( $entries as $entry )
        {
            // ignore hidden files and dirs (2.nd check, should be done before!)
        	if($this->checkIfDeniedNode(basename($entry['href']))) continue;
        	
        	// prevent infinite recursion
            if ( $path === $entry['href'] )
            {
                $this->CFG->t3io->metaftpd_devlog(9,"prevent infinite recursion",__METHOD__, $entry );
            	continue;
            }

            if ( $entry['mimetype'] === self::DIRECTORY_MIMETYPE )
            {
            	// force a trailimg slash
	            if ( $entry['href']{strlen( $entry['href'] ) - 1} !== DS )
		        {
		            $entry['href'] .= DS;
		        }
            	
                // Add collection without any children
                $this->CFG->t3io->metaftpd_devlog(9,"// Add collection without any children (path=$path)",__METHOD__, $entry );
                $contents[] = new ezcWebdavCollection( $entry['href'], $this->getAllProperties( $path ) );
            }
            else
            {
                // If this is not a collection, don't leave a trailing '/'
                // on the href. If you do, Goliath gets confused.
                $entry['href'] = rtrim( $entry['href'], DS );

                // Add files without content
                $this->CFG->t3io->metaftpd_devlog(9,"// Add files without content (path=$path)",__METHOD__, $entry );
                $contents[] = new ezcWebdavResource( $entry['href'], $this->getAllProperties( $path ) );
            }
        }
        
        $this->CFG->t3io->metaftpd_devlog(9,"( $path, $level )",__METHOD__, $contents );
    	
    	return $contents;
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
        $this->CFG->t3io->metaftpd_devlog(10,"( $path )",__METHOD__, get_defined_vars() );
    	
    	$storage = new ezcWebdavBasicPropertyStorage();

        // @todo implement property storage
        return $storage;
    }
    
/**
     * Returns an array with information about the node with path $path.
     *
     * The returned array is of this form:
     * <code>
     * array( 'nodeExists' => boolean, 'isCollection' => boolean )
     * </code>
     *
     * @param string $path
     * @return array(string=>boolean)
     */
    protected function getNodeInfo( $requestUri, $source = null )
    {
        $path = ( $source === null ) ? $requestUri : $source;
        
        
//        if(stripos($path, 'Neuer')!==false) $this->CFG->t3io->metaftpd_devlog(300,__LINE__,__METHOD__, $_SERVER );
        
        // hidden files and dirs
        if($this->checkIfDeniedNode(basename($path))) 
        {
			$data = array();
			$data['nodeExists'] = false;
			$data['isCollection'] = false;
			
			return $data;
        }

        $fullPath = $path;
        $target = $this->splitFirstPathElement( $path, $currentRootFolder );

        if ( !$currentRootFolder )
        {
            $data = $this->fetchVirtualRootFolders( $fullPath, $currentSite, 0, array() );
            $data = $data[0];
            $data['nodeExists'] = true;
            $data['isCollection'] = true;
        }
        else
        {
            if ( !in_array( $currentRootFolder, array( T3_FTPD_FILE_ROOT, T3_FTPD_WWW_ROOT ) ) )
            {
                $data = array();
                $data['nodeExists'] = false;
                $data['isCollection'] = false;
            }
            else
            {
                if ( $target === "" )
                {
//                    // @todo: move this hack to correct function
                	$virtualEntry["name"]     = $currentRootFolder;
			        $virtualEntry["size"]     = 0;
			        $virtualEntry["mimetype"] = self::DIRECTORY_MIMETYPE;
			        $virtualEntry["ctime"]    = time();
			        $virtualEntry["mtime"]    = time();
			        $virtualEntry["href"]     = $requestUri;
			        $data[] = $virtualEntry;
//	                $data = array();
//	                $data['nodeExists'] = false;
//	                $data['isCollection'] = false;
                }
                else
                {
                	$this->CFG->t3io->metaftpd_devlog(13,__LINE__,__METHOD__, get_defined_vars() );
                	
                    $data = $this->getCollectionContent( $fullPath, 0, array() );
                }

                if ( is_array( $data ) )
                {
                    $this->CFG->t3io->metaftpd_devlog(11,__LINE__,__METHOD__, get_defined_vars() );
                    
                	foreach ($data as $k => $singleNodeInfo)
                    {
                    	$pathOfNode = $singleNodeInfo['href'];
                    	if ($pathOfNode && !$this->cachedProperties[$pathOfNode])
                    	{
                    		$this->cachedProperties[$pathOfNode] = $singleNodeInfo;
                    	}                    		
                    }
                    // TODO: get the proper info from data-array 
                    $nodeID = count(explode(DS, $fullPath))-3;
                    if($nodeID < 0) $nodeID = 0;
//                	if($data[0]) 
//                	{
//                		$data['name'] = end(explode(DS, $this->CFG->t3io->_unslashify($fullPath)));
//                    	$data['nodeExists'] = true;
//                    	$data['isCollection'] = ( $data['mimetype'] === self::DIRECTORY_MIMETYPE );
//                    	$data['href'] = $fullPath;
//                	}
					$data = $data[$nodeID];
                    $data['nodeExists'] = ( $data['ctime'] ) ? true : false;
                    //$data['nodeExists'] = true;
                    $data['isCollection'] = ( $data['mimetype'] === self::DIRECTORY_MIMETYPE );
                	
                }
                else
                {
                    $data = array();
                    $data['nodeExists'] = false;
                    $data['isCollection'] = false;
                }
            }
        }
        
        $this->CFG->t3io->metaftpd_devlog(11,__LINE__,__METHOD__, get_defined_vars() );
    	
    	return $data;
    }
    
    /**
     * Builds and returns the content of the virtual start folder for a site.
     *
     * The virtual startfolder is an intermediate step between the site-list
     * and actual content. This directory contains the "content" folder which
     * leads to the site's actual content.
     *
     * An entry in the the returned array is of this form:
     * <code>
     * array( 'name' => node name (eg. 'Group picture'),
     *        'size' => storage size of the_node in bytes (eg. 57123),
     *        'mimetype' => mime type of the node (eg. 'image/jpeg'),
     *        'ctime' => creation time as timestamp,
     *        'mtime' => latest modification time as timestamp,
     *        'href' => the path to the node (eg. '/plain_site_user/Content/Folder1/file1.jpg')
     * </code>
     *
     * @param string $target Eg. '/plain_site_user/Content/Folder1'
     * @param string $site Eg. 'plain_site_user
     * @param string $depth One of -1 (infinite), 0, 1
     * @param array(string) $properties Currently not used
     * @return array(array(string=>mixed))
     */
    protected function fetchVirtualRootFolders( $target, $site, $depth, $properties )
    {
        $this->CFG->t3io->metaftpd_devlog(10,"( $target, $site, $depth )",__METHOD__, get_defined_vars() );
    	
    	$requestUri = $target;

        // Always add the current collection
        $contentEntry = array();
        $scriptURL = $requestUri;
        if ( $scriptURL{strlen( $scriptURL ) - 1} !== '/' )
        {
            $scriptURL .= "/";
        }
        $contentEntry["name"]     = $scriptURL;
        $contentEntry["size"]     = 0;
        $contentEntry["mimetype"] = self::DIRECTORY_MIMETYPE;
        $contentEntry["ctime"]    = time();
        $contentEntry["mtime"]    = time();
        $contentEntry["href"]     = $requestUri;
        $entries[] = $contentEntry;

        $defctime = $contentEntry['ctime'];
        $defmtime = $contentEntry['mtime'];

		// Set up attributes for the virtual content folder:
		foreach ( array( T3_FTPD_FILE_ROOT, T3_FTPD_WWW_ROOT ) as $name )
		{
			$entry             = array();
			$entry["name"]     = $name;
			$entry["size"]     = 0;
			$entry["mimetype"] = self::DIRECTORY_MIMETYPE;
			$entry["ctime"]    = $defctime;
			$entry["mtime"]    = $defmtime;
			$entry["href"]     = $scriptURL . $name;

			$entries[]         = $entry;
		}

        if ( $depth > 0 )
        {
            $scriptURL = $requestUri;
            if ( $scriptURL{strlen( $scriptURL ) - 1} !== '/' )
            {
                $scriptURL .= "/";
            }
        }

        $this->CFG->t3io->metaftpd_devlog(17,"exit",__METHOD__, $entries );
    	
    	return $entries;
    }
    
	/**
     * Handles collections on the virtual folder level, if no virtual folder
     * elements are accessed it lists the virtual folders.
     *
     * An entry in the the returned array is of this form:
     * <code>
     * array( 'name' => node name (eg. 'Group picture'),
     *        'size' => storage size of the_node in bytes (eg. 57123),
     *        'mimetype' => mime type of the node (eg. 'image/jpeg'),
     *        'ctime' => creation time as timestamp,
     *        'mtime' => latest modification time as timestamp,
     *        'href' => the path to the node (eg. '/plain_site_user/Content/Folder1/file1.jpg')
     * </code>
     *
     * @param string $site Eg. 'plain_site_user
     * @param string $collection Eg. 'Folder1'
     * @param string $fullPath Eg. '/plain_site_user/Content/Folder1'
     * @param string $depth One of -1 (infinite), 0, 1
     * @param array(string) $properties Currently not used
     * @return array(array(string=>mixed))
     */
    protected function getVirtualFolderCollection( $currentRootFolder, $collection, $fullPath, $depth, $properties )
    {
    	$this->CFG->t3io->metaftpd_devlog(10,"( $currentRootFolder, $collection, $fullPath )",__METHOD__, get_defined_vars() );
    	
    	if ( $currentRootFolder && !in_array( $currentRootFolder, array( T3_FTPD_FILE_ROOT, T3_FTPD_WWW_ROOT ) ) )
        {
            return null; // self::FAILED_NOT_FOUND;
        }

        $collection = $this->splitFirstPathElement( $collection, $virtualNode );
        
        if($this->checkIfDeniedNode($virtualNode)) 
        {
        	return null; // ignore meta-data
        }
        
		$ret = $this->getContentTreeCollection( $currentRootFolder, $virtualNode, $collection, $fullPath, $depth, $properties );
        
        $this->CFG->t3io->metaftpd_devlog(13,"( $path )",__METHOD__, get_defined_vars() );

        return $ret;
    }

    /**
     * Produces the collection content.
     *
     * Builds either the virtual start folder with the virtual content folder
     * in it (and additional files). OR: if we're browsing within the content
     * folder: it gets the content of the target/given folder.
     *
     * @param string $collection Eg. '/plain_site_user/Content/Folder1'
     * @param int $depth One of -1 (infinite), 0, 1
     * @param array(string) $properties Currently not used
     * @return array(string=>array())
     */
    protected function getCollectionContent( $collection, $depth = false, $properties = false )
    {
        $this->CFG->t3io->metaftpd_devlog(10,"( $collection )",__METHOD__, get_defined_vars() );
    	
    	$fullPath = $collection;
        $collection = $this->splitFirstPathElement( $collection, $currentVirtualFolder );
        $content = $this->getVirtualFolderCollection( $currentVirtualFolder, $collection, $fullPath, $depth, $properties );
        
        $this->CFG->t3io->metaftpd_devlog(13,"( $path )",__METHOD__, get_defined_vars() );

        return $content;
    }

    /**
     * Handles collections on the content tree level.
     *
     * Depending on the virtual folder we will generate a node path url and fetch
     * the nodes for that path.
     *
     * An entry in the the returned array is of this form:
     * <code>
     * array( 'name' => node name (eg. 'Folder1'),
     *        'size' => storage size of the_node in bytes (eg. 4096 for collections),
     *        'mimetype' => mime type of the node (eg. 'httpd/unix-directory'),
     *        'ctime' => creation time as timestamp,
     *        'mtime' => latest modification time as timestamp,
     *        'href' => the path to the node (eg. '/plain_site_user/Content/Folder1/')
     * </code>
     *
     * @param string $site Eg. 'plain_site_user
     * @param string $virtualFolder Eg. 'Content'
     * @param string $collection Eg. 'Folder1'
     * @param string $fullPath Eg. '/plain_site_user/Content/Folder1/'
     * @param string $depth One of -1 (infinite), 0, 1
     * @param array(string) $properties Currently not used
     * @return array(array(string=>mixed))
     */
    protected function getContentTreeCollection( $currentVirtualFolder, $virtualFolder, $collection, $fullPath, $depth, $properties )
    {
		$this->CFG->t3io->metaftpd_devlog(15,"( $currentVirtualFolder, $virtualFolder, $collection, $fullPath )",__METHOD__, get_defined_vars() );
		
		$rawnodes = array();
		$entries  = array();
		
//		$processedUrl = str_replace($_SERVER['SCRIPT_NAME'], '', $url);
		
		$pass1 = $this->CFG->t3io->isT3($fullPath);
		$this->CFG->t3io->metaftpd_devlog(15,'$path isT3 ?' ,__METHOD__, $pass1);
		
		if( $pass1['isWebmount'] || $pass1['isFilemount'] )
		{
			$pass2 = $this->CFG->t3io->T3IsDir($fullPath);
			
			$this->CFG->t3io->metaftpd_devlog(15,'$path is a dir ?' ,__METHOD__, array('$pass2'=>$pass2));
			
			if( $pass2 )
			{
				$fullPath = $this->CFG->t3io->_slashify($fullPath);
			}
		}
		
		$this->CFG->t3io->metaftpd_devlog(15,"passed 1 + 2" ,__METHOD__, get_defined_vars() );
		
		if( substr($fullPath, -1) == DS) // current node is a dir
		{		
			// get curr dir's contents
			$_currDirContents = $this->CFG->t3io->T3ListDir($fullPath);
			$this->CFG->t3io->metaftpd_devlog(15,"get curr dir's contents..." ,__METHOD__, get_defined_vars() );
			
			if(is_array($_currDirContents))
			{
				//turn every leaf into a collection
				foreach($_currDirContents as $_node )
				{	
					$this->CFG->t3io->metaftpd_devlog(16,'foreach($_currDirContents as $_node )' ,__METHOD__, get_defined_vars());
					
					$rawnodes[$this->nodeEncode($_node)] = $this->CFG->t3io->T3IsDir($fullPath.$_node) ? array() : $this->nodeEncode($_node);
				}
			}
			
			$this->CFG->t3io->metaftpd_devlog(15,'just built $rawnodes:',__METHOD__, get_defined_vars());

	        // Always prepend the information about the upper levels
	        $currPathAtoms 	= explode(DS, $this->CFG->t3io->_unslashify($fullPath));
	        foreach ($currPathAtoms as $currPathAtom)
	        {
		        $currNodeName 	= $currPathAtom;
		        if($currNodeName != '')
		        {
			        $thisNodeInfo 	= $this->fetchNodeInfo( $currPath, $currNodeName );
		        	$entries[] 		= $thisNodeInfo;
		        }
			    $currPath 		.= $this->CFG->t3io->_slashify($currPathAtom);
	        }
			
	        // append the subordinated nodes
			foreach ($rawnodes as $nodeName => $nodeContent)
			{
				$entries[] = $this->fetchNodeInfo( $fullPath, $nodeName );
			}
		}
		
		$this->CFG->t3io->metaftpd_devlog(15,'exit',__METHOD__, $entries);
		
		return $entries;
    }
    
	/**
     * Takes the first path element from \a $path and removes it from
     * the path, the extracted part will be placed in \a $name.
     *
     * <code>
     * $path = '/path/to/item/';
     * $newPath = self::splitFirstPathElement( $path, $root );
     * print( $root ); // prints 'path', $newPath is now 'to/item/'
     * $newPath = self::splitFirstPathElement( $newPath, $second );
     * print( $second ); // prints 'to', $newPath is now 'item/'
     * $newPath = self::splitFirstPathElement( $newPath, $third );
     * print( $third ); // prints 'item', $newPath is now ''
     * </code>
     * @param string $path A path of elements delimited by a slash, if the path ends with a slash it will be removed
     * @param string &$element The name of the first path element without any slashes
     * @return string The rest of the path without the ending slash
     * @todo remove or replace
     */
    protected function splitFirstPathElement( $path, &$element )
    {
        if ( $path[0] === '/' )
        {
            $path = substr( $path, 1 );
        }
        $pos = strpos( $path, '/' );
        if ( $pos === false )
        {
            $element = $path;
            $path = '';
        }
        else
        {
            $element = substr( $path, 0, $pos );
            $path = substr( $path, $pos + 1 );
        }
        return $path;
    }
    
	/**
     * Takes the last path element from \a $path and removes it from
     * the path, the extracted part will be placed in \a $name.
     *
     * <code>
     * $path = '/path/to/item/';
     * $newPath = self::splitLastPathElement( $path, $root );
     * print( $root ); // prints 'item', $newPath is now '/path/to'
     * $newPath = self::splitLastPathElement( $newPath, $second );
     * print( $second ); // prints 'to', $newPath is now '/path'
     * $newPath = self::splitLastPathElement( $newPath, $third );
     * print( $third ); // prints 'path', $newPath is now ''
     * </code>
     * @param string $path A path of elements delimited by a slash, if the path ends with a slash it will be removed
     * @param string &$element The name of the first path element without any slashes
     * @return string The rest of the path without the ending slash
     * @todo remove or replace
     */
    protected function splitLastPathElement( $path, &$element )
    {
        $len = strlen( $path );
        if ( $len > 0 and $path[$len - 1] === '/' )
        {
            $path = substr( $path, 0, $len - 1 );
        }
        $pos = strrpos( $path, '/' );
        if ( $pos === false )
        {
            $element = $path;
            $path = '';
        }
        else
        {
            $element = substr( $path, $pos + 1 );
            $path = substr( $path, 0, $pos );
        }
        return $path;
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

    /**
     * Gathers information about a given node specified as parameter.
     *
     * The format of the returned array is:
     * <code>
     * array( 'name' => node name (eg. 'Group picture'),
     *        'size' => storage size of the_node in bytes (eg. 57123),
     *        'mimetype' => mime type of the node (eg. 'image/jpeg'),
     *        'ctime' => creation time as timestamp,
     *        'mtime' => latest modification time as timestamp,
     *        'href' => the path to the node (eg. '/plain_site_user/Content/Folder1/file1.jpg')
     * </code>
     *
     * @param string $target Eg. '/plain_site_user/Content/Folder1/file1.jpg
     * @param eZContentObject &$node The node corresponding to $target
     * @return array(string=>mixed)
     * @todo remove/replace .ini calls, eZContentUpload, eZMimeType, eZSys RequestURI
     * @todo handle articles as files
     */
    protected function fetchNodeInfo( $target, $nodeName )
    {
    	$path 		= $target.$nodeName;    	
    	$fileinfo	= $this->CFG->t3io->T3IsFile( $path );
    	$isDir 		= $this->CFG->t3io->T3IsDir( $path, $fileinfo );
    	$ctime 		= $this->CFG->t3io->T3FileCTime( $path, $fileinfo ) ; 
    	$mtime 		= $this->CFG->t3io->T3FileMTime( $path, $fileinfo ) ; 
    	$filesize	= $isDir ?
    		self::DEFAULT_SIZE:
    		$this->CFG->t3io->T3FileSize( $path, $fileinfo );
    	
//    	if($isDir && $path[strlen($path-1)]!=DS)
//    	{
//    		$path .= DS;
//    	}
    	
        $node = array( 
        	'name' 		=> $nodeName,
			'size' 		=> $filesize,
			'mimetype' 	=> $isDir ? self::DIRECTORY_MIMETYPE : self::DEFAULT_MIMETYPE,
			'ctime' 	=> $ctime,
			'mtime' 	=> $mtime,
			'href' 		=> $path
        );
        
    	$this->CFG->t3io->metaftpd_devlog(9,"( $target, $nodeName )",__METHOD__, get_defined_vars());

        return $node;
    }
    
    /**
     * Check if access to file should be denied. If so. returns boolean true
     * @param string $fileName The Filename to check (e.g. 'file1.jpg')
     * @return bool
     */
    protected function checkIfDeniedNode($nodeName)
    {
    	// locking currently not supported
    	//if(in_array($nodeName, array($this->lockFilename, $this->propertyStoragePath))) return false;
    	
    	if(in_array($nodeName, $this->allowFiles)) return false;
    	foreach ($this->denyFiles as $pattern)
    	{
			if(fnmatch($pattern, $nodeName)) 
			{
				$this->CFG->t3io->metaftpd_devlog(25,"( $nodeName )",__METHOD__, get_defined_vars());
				return true;
			}
    	}
    	return false;
    }
    
//    public function performRequest( ezcWebdavRequest $request )
//    {
//    	$this->CFG->t3io->metaftpd_devlog(300,"( )",__METHOD__, $_SERVER['REQUEST_METHOD']);
//    	parent::performRequest( $request );
//    }
}