<?php

class nwWebdavAuth extends ezcWebdavDigestAuthenticatorBase
implements ezcWebdavLockAuthorizer
{
	protected $properties = array(
		'CFG' => null
	);
	protected $credentials = array();
	protected $tokens;
	protected $tokensStorageFile;
	
	public function __construct($tokensStorageFile=null)
	{
		$this->tokensStorageFile = $tokensStorageFile;
		$this->tokens = array();
		if(file_exists($this->tokensStorageFile))
		{
			$this->tokens = include $this->tokensStorageFile;
		}
	}
	
	public function __destruct()
	{
		if(
			$this->tokens !== array()
//			&& file_exists($this->tokensStorageFile)
		)
		{
			file_put_contents(
				$this->tokensStorageFile,
				"<?php\n\nreturn ".var_export($this->tokens, true).";\n\n?>"
			);
		}
	}
	
	public function assignLock( $user, $lockToken )
    {
        if ( !isset( $this->tokens[$user] ) )
        {
            $this->tokens[$user] = array();
        }
        $this->tokens[$user][$lockToken] = true;
    }
    
    public function ownsLock( $user, $lockToken )
    {
        return ( isset( $this->tokens[$user][$lockToken] ) );
    }
    
    public function releaseLock( $user, $lockToken )
    {
        unset( $this->tokens[$user][$lockToken] );
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
			case 'CFG':
				if(
					!$propertyValue->t3io
					|| is_a($propertyValue->t3io, 'tx_metaftpd_t3io') === false
				)
				{
					throw new ezcBaseValueException($propertyName, $propertyValue, 'tx_metaftpd_t3io not found');
				}
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
	
//	public function getCFG()
//	{
//		return $this->CFG;
//	}

	public function authenticateAnonymous( ezcWebdavAnonymousAuth $data )
	{
		return false;
	}

	public function authenticateBasic( ezcWebdavBasicAuth $data )
	{
		$username = $data->username;
		$password = $data->password;

		if($auth=$this->CFG->t3io->T3Authenticate($username,$password)){
			
			$this->credentials[$username] = $password;
		
			$this->CFG->t3io->metaftpd_devlog(100,"return true",__METHOD__, get_defined_vars() );
		
			return true;
		}
		
		$this->CFG->t3io->metaftpd_devlog(100,"return false",__METHOD__, get_defined_vars() );
		
		return false;
	}

	public function authenticateDigest( ezcWebdavDigestAuth $data )
	{
		$username = $data->username;
		
		$test_t3BeUser = $this->CFG->T3DB->exec_SELECTgetRows('be_users.password','be_users',"be_users.username='$username'", '', '',1);
		
		$this->CFG->t3io->metaftpd_devlog(100,"entry",__METHOD__, get_defined_vars() );
		
		if(count($test_t3BeUser))
		{
			/* TODO: we need to store PLAIN-TEXT-PWs! see
			 * or we have to store the hash of "user:realm:password"
			 * with realm is here 'eZ Components WebDAV'
			 */ 
			$this->credentials[$username] = $test_t3BeUser[0]['password'];
		}
		
		if ( !isset( $this->credentials[$username] ) )
		{
			$this->CFG->t3io->metaftpd_devlog(100,"return false",__METHOD__, get_defined_vars() );
			
			return false;
		}
		
		if($this->checkDigest( $data, $this->credentials[$username] ))
		{
			list($username, $password) = each($this->credentials);
			
			$this->CFG->t3io->T3Identify($username);
			
			$this->CFG->t3io->metaftpd_devlog(100,"return true",__METHOD__, get_defined_vars() );
			
			return true;
		}
		
		$this->CFG->t3io->metaftpd_devlog(100,"exit false",__METHOD__, get_defined_vars() );
		
		return false;
		
	}

	public function authorize( $user, $path, $access = ezcWebdavAuthorizer::ACCESS_READ )
	{
		// ezcWebdav fetches authorisation for every path-segment,
		// but we did this already while building the initial content-tree
		// TODO: fetch fine grained rights from typo3 (read, write, list etc)
		$this->CFG->t3io->metaftpd_devlog(4,"($user, $path, $access)",__METHOD__,get_defined_vars());
		return true;
	}
	
	/**
	 * Calculates the digest according to $data and $password and checks it.
	 * 
	 * As TYPO3 already stores BEUser's passwords md5-encrypted, we can't build the $ha1 string accordingly.
	 * Only solution is to store the whole $ha1-string ("user:realm:password") in the TYPO_DB.
	 * TODO: create BE-Modul, extend the TCA, configure the realm via flexform
	 * 
	 * @param ezcWebdavDigestAuth $data
	 * @param string $password
	 */
	protected function checkDigest( ezcWebdavDigestAuth $data, $password )
    {
        $ha1 = $password;
        $ha2 = md5( "{$data->requestMethod}:{$data->uri}" );

        $digest = null;
        if ( !empty( $data->nonceCount ) && !empty( $data->clientNonce ) && !empty( $data->qualityOfProtection ) )
        {
            // New digest (RFC 2617)
            $digest = md5(
                "{$ha1}:{$data->nonce}:{$data->nonceCount}:{$data->clientNonce}:{$data->qualityOfProtection}:{$ha2}"
            );
        }
        else
        {
            // Old digest (RFC 2069)
            $digest = md5( "{$ha1}:{$data->nonce}:{$ha2}" );
        }

        return $digest === $data->response;
    }
}