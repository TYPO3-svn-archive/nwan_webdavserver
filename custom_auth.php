<?php

class t3Auth extends ezcWebdavDigestAuthenticatorBase
implements ezcWebdavAuthorizer
{
	private $CFG;
	protected $credentials = array();
	
	function __construct($_CFG){
		
		$this->CFG = $_CFG;
	}
	
	public function getCFG(){
		return $this->CFG;
	}
	
	public function initT3User($username, $password){
		$this->CFG->t3io->T3Authenticate($username,$password);
		return $this->CFG;
	}

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
			return true;
		}
		
		return false;
	}

	public function authenticateDigest( ezcWebdavDigestAuth $data )
	{
		$username = $data->username;

		if ( !isset( $this->credentials[$username] ) )
		{
			return false;
		}
		return ( $this->checkDigest( $data, $this->credentials[$username] ) );
	}

	public function authorize( $user, $path, $access = ezcWebdavAuthorizer::ACCESS_READ )
	{
		// ezcWebdav fetches authorisation for every path-segment,
		// but we did this already while builing the initial content-tree
		// TODO: fetch fine grained rights from typo3 (read, write, list etc)
		return true;
	}
}
?>