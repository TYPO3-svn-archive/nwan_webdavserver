<?php

class t3Auth extends ezcWebdavDigestAuthenticatorBase
implements ezcWebdavAuthorizer
{
	private $CFG;
	protected $credentials = array();
	
	function __construct(&$_CFG){
		
		$this->CFG = $_CFG;
	}
	
	public function getCFG(){
		return $this->CFG;
	}

	public function authenticateAnonymous( ezcWebdavAnonymousAuth $data )
	{
		return false;
	}

	public function authenticateBasic( ezcWebdavBasicAuth $data )
	{
		$this->CFG->t3io->metaftpd_devlog(1,print_r($data,1),basename(__FILE__).':'.__LINE__,'ServeRequest');
		
		$username = $data->username;
		$password = $data->password;

		if($auth=$this->CFG->t3io->T3Authenticate($username,$password)){
			
			$this->credentials[$username] = $password;
			return true;
		}
		
		return false;
	}

	public function authenticateDigest( ezcWebdavDigestAuth $data )
	{
		$this->CFG->t3io->metaftpd_devlog(1,print_r($data,1),basename(__FILE__).':'.__LINE__,'ServeRequest');
		
		$username = $data->username;
		
		$this->CFG->t3io->metaftpd_devlog(1,print_r($data,1),basename(__FILE__).':'.__LINE__,'ServeRequest');
		
		$test_t3BeUser = $this->CFG->T3DB->exec_SELECTgetRows('be_users.password','be_users',"be_users.username='$username'", '', '',1);
		
		$this->CFG->t3io->metaftpd_devlog(1,print_r($test_t3BeUser,1),basename(__FILE__).':'.__LINE__,'ServeRequest');
		
		if(count($test_t3BeUser))
		{
			/* TODO: we need to store PLAIN-TEXT-PWs! see
			 * or we have to store the hash of "user:realm:password"
			 * with realm is here 'eZ Components WebDAV'
			 */ 
			$this->credentials[$username] = $test_t3BeUser[0]['password'];
		}
		
		$this->CFG->t3io->metaftpd_devlog(1,print_r($this->credentials,1),basename(__FILE__).':'.__LINE__,'ServeRequest');

		if ( !isset( $this->credentials[$username] ) )
		{
			return false;
		}
		
		if($this->checkDigest( $data, $this->credentials[$username] ))
		{
			list($username, $password) = each($this->credentials);
			
			$this->CFG->t3io->T3Authenticate($username,$password);
			
			return true;
		}
		
		return false;
		
	}

	public function authorize( $user, $path, $access = ezcWebdavAuthorizer::ACCESS_READ )
	{
		// ezcWebdav fetches authorisation for every path-segment,
		// but we did this already while building the initial content-tree
		// TODO: fetch fine grained rights from typo3 (read, write, list etc)
		return true;
	}
	
	/**
	 * Calculates the digest according to $data and $password and checks it.
	 * 
	 * As TYPO3 aleady BEUser's passwords md5-encrypted, we can't build the $ha1 string accordingly.
	 * Only soolution is to store the whole $ha1-string ("user:realm:password") in the TYPO_DB.
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
?>