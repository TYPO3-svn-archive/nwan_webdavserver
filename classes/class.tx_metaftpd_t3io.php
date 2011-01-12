<?
/***************************************************************
*  Copyright notice
*
*  (c) 2006 Christophe BALISKY (cbalisky@metaphore.fr)
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*f
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
 * This is a API for remote controlling TYPO3.
 * See documentation or extensions 'meta_ftpd' for examples on how to use this plugin
 *
 * @author	Christophe BALISKY <cbalisky@metaphore.fr>
 */

require_once(PATH_t3lib.'class.t3lib_tcemain.php');
require_once(PATH_t3lib.'class.t3lib_befunc.php');
require_once(PATH_t3lib.'class.t3lib_div.php');
require_once (PATH_t3lib .'class.t3lib_flexformtools.php');
require_once(PATH_t3lib.'class.t3lib_transferdata.php');
define('t3prefix','T3-');
define('t3pidsep','-');
define('t3ctypesep','-');
define('t3ctypetitlesep','-');

class tx_metaftpd_t3io {
	var $CFG;
	var $BEUSER;
	var $T3FILE;
	var $user_uid;
	var $user_gid;

// Initialisation of class instance.
	
function T3Init(&$CFG) {
	$this->CFG=$CFG;
}


// Checks User authentification 

function T3Authenticate($username,$password,$crypt="md5") 
{
	$this->metaftpd_devlog(1,'$username:'.$username,basename(__FILE__).':'.__LINE__,'T3Authenticate');
	$auth=false;
	
	// We check password type 
	switch ($crypt) 
	{
			case "md5":
			    $pass = md5($password);
			    break;
			case "plain":
			    $pass = $password;
			    break;
	 }
	 
	 $auth = $this->T3Identify($username, $checkPassword=true, $pass);
	
	$this->metaftpd_devlog(1,"T3Authenticate returns: {$auth}",basename(__FILE__).':'.__LINE__,'T3Authenticate');
	
	return $auth;  
}

function T3Identify($username, $checkPassword=false, $password=null)
{
	$this->metaftpd_devlog(1,"T3Identify:".$username,basename(__FILE__).':'.__LINE__,'T3Authenticate');
	
	// We check if BE User exists in TYPO3 DB, // What about FE Users ?
	 $res=$this->CFG->T3DB->exec_SELECTquery
	 (
	 	'uid,usergroup,password',
	 	'be_users',
	 	// TODO: ask also for start- and stop-dates
	 	'be_users.username="'.$username.'" and be_users.deleted=0 and be_users.disable=0',
	 	'',
	 	'',
	 	1
	 );
	 
	 if ($res) 
	 {
	 	$resu=$GLOBALS['TYPO3_DB']->sql_num_rows($res);
	 	if ($resu) 
	 	{

			// We get user info from database
			$userinfo = mysql_fetch_assoc($res);
			
			// if requested, check authentication
			if(
				$checkPassword
				&& $userinfo['password']!=$password
			)
			{
				$auth = false;
			}
			else
			{
				// unique ID
				$this->user_uid = $userinfo['uid'];
				
				// Group ID
				$this->user_gid = $userinfo['usergroup'];
		 		$new_BE_USER = t3lib_div::makeInstance("t3lib_beUserAuth");     // New backend user object
	      		$new_BE_USER->OS = TYPO3_OS;
			
				// We create BE USER
				$new_BE_USER->setBeUserByUid($this->user_uid);
				$new_BE_USER->fetchGroupData();
				$this->BEUSER=$new_BE_USER;
				
				$FILEMOUNTS=$this->BEUSER->groupData['filemounts'];
				$WEBMOUNTS=$this->BEUSER->groupData['webmounts'];
	
				$this->metaftpd_devlog(1,"user_uid: {$this->user_uid} , FILEMOUNTS: ".print_r($FILEMOUNTS,1).", WEBMOUNTS: {$WEBMOUNTS}",basename(__FILE__).':'.__LINE__,'T3Authenticate');
				
				$T3EXTFILE=t3lib_div::makeInstance('t3lib_extFileFunctions');
		 		$T3EXTFILE->init($FILEMOUNTS, $TYPO3_CONF_VARS['BE']['fileExtensions']); // CBY get it from connected user
		 		$T3EXTFILE->init_actionPerms(1); // CBY get it from connected user
		 		$this->T3FILE=$T3EXTFILE;
				
		 		$auth=true;
			}
    	} 	
    	else 
    	{
			$auth=false;
		}
	} 
	else 
	{
		$this->metaftpd_devlog(1,"T3Authenticate : auth failed with db",basename(__FILE__).':'.__LINE__,'T3Authenticate');
	}
	return $auth;
}
	
// this function gets PID from directoryname
	
function T3GetPid($path) {
		$this->metaftpd_devlog(4,"T3GetPid : $path",'meta_t3io');
		$pid=0;
		$p1=strpos($path,t3prefix);
		$this->metaftpd_devlog(4,"T3GetPid : p1 $p1",'meta_t3io');
		if ($p1!==false) {
			$path2=str_replace(t3prefix,'',$path);
			$p=strpos($path2,t3pidsep);
			$this->metaftpd_devlog(4,"T3GetPid : path2  $path2 p $p",'meta_t3io');
		  if ($p!==false) {
		  	$pid=intval(substr($path2,0,$p));
			} 
		}
		
		$this->metaftpd_devlog(4,"T3GetPid out: $pid",'meta_t3io');
		return $pid;
  }
  
// this function gets UID from filename.

function T3GetFileUid($path) {
		$this->metaftpd_devlog(4,"T3GetFileUid : $path",'meta_t3io');
	  $patharr=explode('/',$path);
	  $path=array_pop($patharr);
		$p1=strpos($path,t3prefix);
		$uid=0;
		if ($p1!==false) {
			$path2=str_replace(t3prefix,'',$path);
			$p=strpos($path2,t3pidsep);

		  if ($p!==false) {
		  	$uid= intval(substr($path2,0,$p));
			}
		}		
 		$this->metaftpd_devlog(4,"T3GetFileUid out: $uid",'meta_t3io');
		return $uid;
 }
	
/* Check if path is a T3 ressource and we are allowed to access it */
// $path is relative path to webdav root $siteroot/webdav/$relPath

	function isT3($path) {
		$this->metaftpd_devlog(1,"========= isT3 : $path",'meta_t3io','isT3');
		$path=trim($path);
		$ret=array();
		$ret['prm']=$path;
		$ret['cwd']=$this->cwd;
		$ret['newcwd']=$this->T3CleanFilePath($path);
		$darr=t3lib_div::trimexplode('/',$ret['newcwd']);	
		$ret['rootline']=$darr;
		$ret['level']=count($darr)-1;		
		if (!$darr[$ret['level']]) $ret['level']--;
		$ret['pid']=0;	
		$ret['isWebmount']=0;
		$ret['isFilemount']=0;
		$ret['isAuthorized']=0;
		if ($darr[1]==T3_FTPD_WWW_ROOT) {
			$ret['isWebmount']=1;			
			$ret['pid']=$ret['level']?$this->T3GetPid($ret['rootline'][$ret['level']]):0;
			if (!$ret['pid']) {
				$fpath=$this->_unslashify($ret['newcwd']);
				$fname=basename($fpath);
				str_replace($fname,'',$fpath);
				$info=$this->T3IsFile($fpath);
				if ($info['pid']) $ret['pid']=$info['pid'];
				// must also test if filename is in page of pid ..
		  }
			
			
			
			if ($ret['level']==1)	{
				$ret['isAuthorized']=1;
			} else {
				$ret['isAuthorized']=1;
		  }
		} else if ($darr[1]==T3_FTPD_FILE_ROOT) {
			$ret['isFilemount']=1;			
			if ($ret['level']==1) {
					$ret['isAuthorized']=1;
			} else {
		    	$ret['testcwd']=$this->T3CleanFilePath($this->T3ReplaceMountPointsByPath($ret['newcwd']));
		    	$filename=$this->T3CleanFilePath($this->CFG->T3PHYSICALROOTDIR.$this->T3ReplaceMountPointsByPath($ret['newcwd']));
					$ret['isAuthorized']=(file_exists($filename) && filetype($filename) == "dir" && $this->T3FILE->checkPathAgainstMounts($filename));
			}
		} else if ($ret['level']==0) {
			$ret['isAuthorized']=1;
		}
		$this->metaftpd_devlog(1,"========= isT3 : ".serialize($ret),'meta_t3io','isT3');
		return $ret;
	}
	// Make sur path doesn't end with a /
    function _unslashify($path) 
    {
        if ($path[strlen($path)-1] == '/') {
            $path = substr($path, 0, strlen($path) -1);
        }
        return $path;
    }
/**
     * Slashify - make sure path ends in a slash
     *
     * @param   string directory path
     * @returns string directory path wiht trailing slash
     */
    function _slashify($path) 
    {
        if ($path[strlen($path)-1] != '/') {
            $path = $path."/";
        }
        return $path;
    }

	
// tests both type of files (physical & virtual & gives back info) ...
// $virtualpath (path in webdav browser)
// $physicalpath (physicalpath to linked file)
// $relPhysicalPath
// $relVirtualPath ..


function T3IsFile($virtualpath) {
		$this->metaftpd_devlog(1,"========= T3IsFile start: $virtualpath",'meta_t3io','T3IsFile');
		$virtualpath=$this->T3CleanFilePath($virtualpath); 
		$virtualpath=str_replace($this->CFG->T3ROOTDIR,'',$virtualpath);
		$virtualpath='/'.str_replace($this->CFG->T3PHYSICALROOTDIR,'',$virtualpath);
		$virtualpath=$this->T3CleanFilePath($virtualpath); 
		$fileinfo=array();
		$fileinfo['prm']=$virtualpath;
		$fileinfo['cwd']=$this->cwd;
		$fileinfo['newcwd']=$virtualpath;		
		$darr=t3lib_div::trimexplode('/',$virtualpath);
		$fileinfo['rootline']=$darr;
		$fileinfo['level']=count($darr)-1;
		if (!$darr[$fileinfo['level']]) $fileinfo['level']--;
		$fileinfo['pid']=0;	
		$fileinfo['uid']=0;	
		$fileinfo['isWebmount']=0;
		$fileinfo['isFilemount']=0;
		$fileinfo['isAuthorized']=0;
		$fileinfo['isWebcontent']=0;			
		$fileinfo['isDir']=0;
		$fileinfo['isFile']=0;
		if ($darr[1]==T3_FTPD_WWW_ROOT) {
			$fileinfo['isWebmount']=1;			
			$this->metaftpd_devlog(4,"========= T3IsFile start: ".$fileinfo['level']."k kl".$fileinfo['rootline'][$fileinfo['level']],'meta_t3io','T3IsFile');
		  // PID of page
			$fileinfo['pid']=$fileinfo['level']?$this->T3GetPid($fileinfo['rootline'][$fileinfo['level']-1]):0;
			// UID of content or page
			$fileinfo['uid']=$fileinfo['level']?$this->T3GetPid($fileinfo['rootline'][$fileinfo['level']]):0;
			$enable=$GLOBALS['TSFE']->sys_page->enableFields('pages',-1,array('fe_group'=>1));
			//$this->metaftpd_devlog(4,"isT3 :".$fileinfo['pid']." : ".$fileinfo['uid'],'meta_t3io','T3IsFile');			
			// we get pages 
			
		  if (!$fileinfo['uid']) $this->T3GetUidFromFileName($fileinfo,2);
			
			$res=$this->CFG->T3DB->exec_SELECTquery('tstamp,crdate','pages',"pid='".intval($fileinfo['pid'])."' and uid='".intval($fileinfo['uid'])."' ".$enable );
			$this->metaftpd_devlog(3,"T3IsFile : SQL ,".$this->CFG->T3DB->SELECTquery('tstamp,crdate','pages',"pid='".intval($fileinfo['pid'])."' and uid='".intval($fileinfo['uid'])."' ".$enable ),'meta_t3io','T3IsFile');
			if ($res) {
				while ($row=$this->CFG->T3DB->sql_fetch_assoc($res)) {
					$fileinfo['pcdate']=$row['date'];
					$fileinfo['isDir']=1;
					$fileinfo['pmdate']=$row['tstamp'];
				}
			} else {
				$this->metaftpd_devlog(4,"T3IsFile : PB DB",'meta_t3io');
			}

			// Conditions for page :
			// isWebmount=1
			// $fileinfo['uid'] > 0
			// or page with same title exists in page of pid $fileinfo['pid'].
			
			
		  if (!$fileinfo['uid']) $this->T3GetUidFromFileName($fileinfo,1);
			// We get content (no test on filename and filetype what if content uid and page uid are equal ???? MMCBY

			// We get content
			if ($fileinfo['uid'] && $fileinfo['pid']) {
				$enable=$GLOBALS['TSFE']->sys_page->enableFields('tt_content',-1,array('fe_group'=>1));
				$res=$this->CFG->T3DB->exec_SELECTquery('uid,ctype,header,bodytext,tstamp,tx_metaftpd_ftpfile,date','tt_content',"pid='".intval($fileinfo['pid'])."' and uid='".intval($fileinfo['uid'])."' ".$enable );
				$this->metaftpd_devlog(4,"T3IsFile : SQL ,".$this->CFG->T3DB->SELECTquery('uid,ctype,header,bodytext,tstamp,date','tt_content',"pid='".intval($fileinfo['pid'])."' and uid='".intval($fileinfo['uid'])."' ".$enable ),'meta_t3io','T3IsFile');
				if ($res) 
				{
					while ($row=$this->CFG->T3DB->sql_fetch_assoc($res)) {
						$fileinfo['isWebcontent']=1;
						
						switch ($row['ctype']) {
						case 'html' :
						case 'textpic' :
						case 'text' :
							$fileinfo['isT3File']=1;
							$fileinfo['isFile']=1;
							$fileinfo['isDir']=0;
							$fileinfo['data']=$row['bodytext'];
							$fileinfo['size']=strlen($fileinfo['data']);
							$fileinfo['name']=$row['header'];
							$fileinfo['type']=$row['ctype'];	
							$fileinfo['cdate']=$row['date'];
							$fileinfo['mdate']=$row['tstamp'];
							break;
						default:
							$fileinfo['isT3File']=1;
							$fileinfo['isFile']=1;
							$fileinfo['isDir']=0;
							$fileinfo['name']=$row['header'];
							$fileinfo['type']=$row['ctype'];	
							$fileinfo['cdate']=$row['date'];
							$fileinfo['mdate']=$row['tstamp'];
							$fileinfo['size']=0;
							break;
						}		
						
						if ($row['tx_metaftpd_ftpfile']) 
						{
							$fileinfo['tx_metaftpd_ftpfile']=$fileinfo['pid'].'/'.$row['tx_metaftpd_ftpfile'];
							
							$physicalpath=	$this->T3CleanFilePath($this->T3MakeFilePath($this->CFG->T3PHYSICALROOTDIR.'uploads/tx_metaftpd/'.$fileinfo['tx_metaftpd_ftpfile']));
							if(file_exists($physicalpath))
							{
								$fileinfo['size']=filesize($physicalpath);
								$this->metaftpd_devlog(1,"T3IsFile : path  $path : ".	$fileinfo['size'],'meta_t3io','T3IsFile');
								$fileinfo['cdate']=$this->T3FileCTimeI($physicalpath,$fileinfo);
								$fileinfo['mdate']=$this->T3FileMTimeI($physicalpath,$fileinfo);;
							}
						}
					}
				} else {
					$this->metaftpd_devlog(2,"T3IsFile : PB DB2",'meta_t3io','T3IsFile');
				}
			}


			$fileinfo['isAuthorized']=1; // add T3 rights check here !!

		}	else if ($darr[1]==T3_FTPD_FILE_ROOT) {
			$fileinfo['isFilemount']=1;			
			if ($fileinfo['level']==1) {
				$fileinfo['isAuthorized']=0;
			} else {
			  $fileinfo['testcwd']=$this->T3CleanFilePath($this->T3ReplaceMountPointsByPath($fileinfo['newcwd']));
			  // TO DO 
				$fileinfo['isAuthorized']=(file_exists($this->CFG->T3PHYSICALROOTDIR.$fileinfo['testcwd']) && filetype($this->CFG->T3PHYSICALROOTDIR . $fileinfo['testcwd']) == "dir" && $this->T3FILE->checkPathAgainstMounts($this->CFG->T3ROOTDIR . $fileinfo['testcwd']));
			}
		} else if ($fileinfo['level']==0) {
			$fileinfo['isAuthorized']=0;
		}
		$this->metaftpd_devlog(1,"========= T3IsFile fin : ".serialize($fileinfo),'meta_t3io','T3IsFile');
		return $fileinfo;
	}
	
//checks that two paths are identical (Webmounts if  Pids are equal and filenames differnet we give back false, filemounts we check that paths are not the same).
//MMCBY
function T3CheckFilePathRename($sourceinfo,$destinfo) {	  
	  $this->metaftpd_devlog(1,"======= T3CheckFilePathRename:".serialize($sourceinfo). ' dest '.serialize($destinfo),'meta_t3io','COPY');
		if ($sourceinfo['isWebmount'] && $destinfo['isWebmount'] && $destinfo['uid']==$sourceinfo['uid'] && $destinfo['prm']!=$sourceinfo['prm']) $ret=false;
		$ret=true;
	  $this->metaftpd_devlog(1,"======= T3CheckFilePathRename ret: $ret",'meta_t3io','COPY');
		return $ret;
}

function T3MakePageTitle($row) {
	$ret=t3prefix.$row['uid'].t3pidsep.str_replace('/','',$row['title']);
	return $ret;
}
function T3MakeContentTitle($row,$forceT3=0,$forceheader='') {
	//$ret=t3prefix.$row['uid'].t3pidsep.str_replace('/','',$row['ctype']).'.'.($row['tx_metaftpd_ftpfile']?$row['tx_metaftpd_ftpfile']:str_replace('/','',$row['ctype']));
  if (!$forceT3 && $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['meta_ftpd']['natural_file_names']) $ret=$row['header']; 
  else
		$ret=t3prefix.$row['uid'].t3pidsep.str_replace('/','',$row['ctype']).'.'.($forceheader?$forceheader:$row['header']);
	return $ret;
}

// extracts Page title from t3 filename ...
function T3ExtractPageTitle($uid,$filename) {
	$prefix=t3prefix.$uid.t3pidsep;
	$ret= str_replace($prefix,'',$filename); 
	$this->metaftpd_devlog(1,"======= T3ExtractPageTitle $prefix ret: $ret",'meta_t3io','COPY');
	return $ret;
}



// We prepare upload here ...

function T3IsFileUpload($path) {
	$this->metaftpd_devlog(1,"======= T3IsFileUpload:".$path,'meta_t3io','T3IsFileUpload');
	$path=trim($path);
	$path=str_replace($this->CFG->T3ROOTDIR,'',$path);
	$path=str_replace($this->CFG->T3PHYSICALROOTDIR,'',$path);
	$path=$this->T3CleanFilePath($path);
	$fileinfo=array();
	$fileinfo['prm']=$path;
	$fileinfo['cwd']=$this->cwd;
	$fileinfo['newcwd']=$path;
	$darr=t3lib_div::trimexplode('/',$path);
	$fileinfo['rootline']=$darr;
	$fileinfo['level']=count($darr)-1;
	if (!$darr[$fileinfo['level']]) $fileinfo['level']--;
	$fileinfo['pid']=0;
	$fileinfo['uid']=0;
	$fileinfo['isT3']=0;
	$fileinfo['isT3File']=0;
	$fileinfo['isFile']=0;
	$fileinfo['isDir']=1;
	if ($darr[1]==T3_FTPD_WWW_ROOT) {
		$fileinfo['isWebmount']=1;
		$fileinfo['pid']=$fileinfo['level']?$this->T3GetPid($fileinfo['rootline'][$fileinfo['level']-1]):0;
		$fileinfo['file']=$fileinfo['level']?$fileinfo['rootline'][$fileinfo['level']]:'';
		// we get file extension
			
		if ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['meta_ftpd']['natural_file_names']) {
			$filenamearr=explode('.',$fileinfo['file']);
			$fileinfo['ext']=strtolower(array_pop($filenamearr));
			$fileinfo['filename']=$fileinfo['file'];
		} else {
			$filenamearr=explode('.',$fileinfo['file']);
			$fileinfo['ext']=strtolower(array_pop($filenamearr));
			$c=strpos($fileinfo['file'],'].');
			$fileinfo['filename']=$fileinfo['file'];
			if ($c!==false) $fileinfo['filename']=substr($fileinfo['file'],$c+2);  // ????
		}
			
		$fileinfo['uid']=$fileinfo['level']?intval($this->T3GetFileUid($fileinfo['file'])):0;
		$fileinfo['cmd']='insert';
		$this->metaftpd_devlog(1,"======= T3IsFileUpload info .".serialize($fileinfo),'meta_t3io','T3IsFileUpload');

		$this->T3GetUidFromFileName($fileinfo,1);

		if ($fileinfo['uid']>0) {
			$fileinfo['cmd']='update';

			if (!$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['meta_ftpd']['natural_file_names']) {
				$farr=explode('.',$fileinfo['file']);
				unset($farr[0]);
				$fileinfo['file']=implode('.',$farr);
			}
				
		}
		$fileinfo['filepath']=$this->CFG->T3UPLOADDIR.$fileinfo['file'];
	} else if ($darr[1]==T3_FTPD_FILE_ROOT) {
		$fileinfo['isFilemount']=1;
		$fileinfo['isT3File']=1;
		$fileinfo['isFile']=1;
		$fileinfo['isDir']=0;
		if ($fileinfo['level']<=1) {
			$fileinfo['isAuthorized']=0;
		} else {
			$fileinfo['testcwd']=$this->T3CleanFilePath('/'.$this->T3ReplaceMountPointsByPath($fileinfo['newcwd']));
			$fileinfo['filePath']=$this->T3MakeFilePath($fileinfo['testcwd']);
			$fileinfo['isAuthorized']=(file_exists($fileinfo['filePath']) && filetype($fileinfo['filePath']) == "dir" && $this->T3FILE->checkPathAgainstMounts($fileinfo['filePath']));
		}
	}
	$this->metaftpd_devlog(1,"======= T3IsFileUpload fin.",'meta_t3io','T3IsFileUpload');
	return $fileinfo;
}
	
// gets page uid from filname (what about content...?) MMCBY

function T3GetUidFromFileName(&$fileinfo,$tt_content=0) {
	// if filename is not a T3-pid-... we try to get it from database
	if ($fileinfo['isWebmount'] && $fileinfo['uid']==0 && $fileinfo['pid']) {
		$table='pages';
		$field='title';
		if ($fileinfo['isWebcontent'] || $tt_content==1) {
				$table='tt_content';
				$field='header';
		}
		$enable=$GLOBALS['TSFE']->sys_page->enableFields($table,-1,array('fe_group'=>1));
		$searchtitle=basename($fileinfo['prm']);
		$res=$this->CFG->T3DB->exec_SELECTquery('uid',$table,'pid='.intval($fileinfo['pid']).' AND '.$field.'=\''.$searchtitle.'\''.$enable );
		$this->metaftpd_devlog(4,"T3GetUidFromFileName:".$this->CFG->T3DB->SELECTquery('*',$table,'pid='.intval($fileinfo['pid']).' AND '.$field.'=\''.$searchtitle.'\''.$enable ),'meta_t3io','T3GetUidFromFileName');
		if ($res) {
			while ($row=$this->CFG->T3DB->sql_fetch_assoc($res)) {
			 $fileinfo['uid']=$row['uid'];
		   $this->metaftpd_devlog(1,"T3GetUidFromFileName row:".serialize($row),'meta_t3io','T3GetUidFromFileName');
				break;
			}
		}
	}
}
	
// Here we get we create content data array  from $fileinfo
// We apply system transformations according to uploaded file type specifications
//

function T3GetCTypeFile(&$fileinfo) {
	
	// We load data table TCA configuration
	
	t3lib_div::loadTCA('tt_content');
	
	foreach($fileinfo as $key=>$val) $ress.=$key." : ".$val.chr(10);
	$this->metaftpd_devlog(1,"====== T3GetCTypeFile:".$ress,'meta_t3io','T3GetCTypeFile');
	
	$contentDataArray['pid']=$fileinfo['pid'];
	
	// We load Page tsconfig
	
	$conf=t3lib_BEfunc::getPagesTSconfig($contentDataArray['pid']);

	// We check if there are specific transformations defined for this file extension
	
	if (is_array($conf['plugin.']['tx_metaftpd.'][$fileinfo['ext'].'.']['put.'])) {
	  $extConf=$conf['plugin.']['tx_metaftpd.'][$fileinfo['ext'].'.']['put.'];
	 	if ($extConf['headerField']) $contentDataArray[$extConf['headerField']]=$fileinfo['filename'];
	  if ($extConf['ctypeField'] && $extConf['ctype']) $contentDataArray[$extConf['ctypeField']]=$extConf['ctype'];
		if ($extConf['listTypeField'] && $extConf['list_type'] && $extConf['ctype']=='list') $contentDataArray[$extConf['listTypeField']]=$extConf['list_type'];

		// We copy the transfered file ???

	  $this->T3FileFieldCopy($fileinfo,'tx_metaftpd_ftpfile',$contentDataArray);
		
		// We handle Flex forms  here...
		
		
		if ($extConf['flex']) {
			$flexArray=$this->T3MakeNewFlexFormArray($fileinfo,$contentDataArray,$extConf['flex.']);
			$flexObj = t3lib_div :: makeInstance('t3lib_flexformtools');
			$this->metaftpd_devlog(1,"Flex array  :".serialize($flexArray),'meta_t3io','T3GetCTypeFile');  		
			$contentDataArray['pi_flexform'] = $flexObj->flexArray2Xml($flexArray, true);
	  }
	  
	  // We handle system calls here
	  
		if ($extConf['dataField'] && $extConf['systemTransform']) {
			$charset=$GLOBALS['LANG']->charSet;
			$fieldCFG = $GLOBALS['TCA']['tt_content']['columns']['tx_metaftpd_ftpfile']['config'];
			// checksize must be implemented here !
			$uploaddir='';
			if ($fieldCFG['uploadfolder']) {
  			$this->metaftpd_devlog(1,"T3GetCTypeFile , fielddir: ".$fieldCFG['uploadfolder'],'meta_t3io','T3GetCTypeFile');
  			$uploaddir=$this->CFG->T3PHYSICALROOTDIR.$fieldCFG['uploadfolder'].'/'.$fileinfo['pid'].'/';
				// we create pid dir if it doesn't exist ...
  			
  			if (!is_dir($uploaddir)) {
  				$stat = mkdir($uploaddir, 0777);
      		if (!$stat) {
  					$this->metaftpd_devlog(2,"!!! Error : T3FileFieldCopy, can't create ".$uploaddir,'meta_t3io');
      		}
      	}
      }
      $filepath=$this->T3MakeFilePath($fileinfo['filepath']);
      $markerArray['###CHARSET###']=$charset;
      $markerArray['###UPLOADDIR###']=$uploaddir;
      $markerArray['###FILEPATH###']=$filepath;
      $markerArray['###FILENAME###']=basename($filepath);
      $cmd=$extConf['systemTransform'];
      foreach($markerArray as $key=>$value) {
      	$cmd=str_replace($key,$value,$cmd);
      }
			//$cmd=sprintf($extConf['systemTransform'],$this->T3MakeFilePath($fileinfo['filepath']), $uploaddir, $charset);
			$this->metaftpd_devlog(1,"cmd $cmd",'meta_t3io');
			$this->metaftpd_devlog(1,"=== SYSTEM TRANSFORMATION :".$cmd,'meta_t3io','T3GetCTypeFile');  
			//$this->metaftpd_devlog(4,'ufile : '.$this->T3MakeFilePath($fileinfo['ufile']),'meta_t3io','T3GetCTypeFile');
			$this->metaftpd_devlog(1,'file:'.$this->T3GetFileName($fileinfo['ufile']),'meta_t3io','T3GetCTypeFile');
			$tab=array();
			$r=exec($cmd,&$tab);
			$data=implode($extConf['sep']?$extConf['sep']:'',$tab);
			if (!$r && !$data) {
			  $data="Erreur commande : $cmd , code retour : $r !";
				$this->metaftpd_devlog(1,'Erreur cmd:'.$data,'meta_t3io','T3GetCTypeFile');
			} 
			// Post data process
			
			if ($extConf['replace']) {
				$replaceArray=t3lib_div::trimexplode(':',$extConf['replace']);
				$whatToReplace=$replaceArray[0];
				$whatToReplaceWith=$replaceArray[1];
				$whatToReplaceWith=str_replace('###UPLOADFOLDER###',$fieldCFG['uploadfolder'].'/'.$fileinfo['pid'].'/',$whatToReplaceWith);
				$data=str_replace($whatToReplace,$whatToReplaceWith,$data);
			  $this->metaftpd_devlog(1,"whatToReplace : $whatToReplace whatToReplaceWith $whatToReplaceWith",'meta_t3io');
			}
			$this->metaftpd_devlog(1,'strip:','meta_t3io','T3GetCTypeFile');
			$contentDataArray[$extConf['dataField']]= $this->T3StripComments($this->T3_strip_selected_tags($data,array('head','html','meta','HEAD','HTML','!DOCTYPE','!---')));
		}
		
		// we handle optional file copies here (for plugin for example).
		
	  if ($extConf['fileField']) {
	  	$this->T3FileFieldCopy($fileinfo,$extConf['fileField'],$contentDataArray);
	  }
	} else {
		
		// here we handle default system transformations :
		// - images goto image type
		// - text and ascii types go to text type
		// - the rest is rendered as upload content
		
			switch($fileinfo['ext']) {
			case 'jpg':
			case 'png':
			case 'jpeg':
			case 'tif':
			case 'bmp':
			case 'gif':
				$contentDataArray['ctype']='image';
				//$contentDataArray['image']=$fileinfo['file'];
				$contentDataArray['header']=$fileinfo['filename'];
	      $this->T3FileFieldCopy($fileinfo,'image',$contentDataArray);
				break;
			case 'html':
			case 'htm':
			case 'txt':
			case 'sql':
			case 'php':
			case 'c':
			case 'js':
			case 'css':
			case 'csv':
			case 'text':
			case 'xml':
				$contentDataArray['ctype']='text';
				$contentDataArray['bodytext']=$fileinfo['data'];
				$contentDataArray['header']=$fileinfo['filename'];
				break;
			default :
				$contentDataArray['ctype']='uploads';
				$contentDataArray['header']=$fileinfo['filename'];
				//$contentDataArray['media']=$fileinfo['file'];
	      $this->T3FileFieldCopy($fileinfo,'media',$contentDataArray);
				break;
			}
	    $this->T3FileFieldCopy($fileinfo,'tx_metaftpd_ftpfile',$contentDataArray);
		}
		$this->metaftpd_devlog(1,"=======FIN GET CTYPE :",'meta_t3io','T3GetCTypeFile');  
		return $contentDataArray;
	}

function T3MakeNewFlexFormArray($res,$row,$flexconf) {
		if (!$flexconf['field']) {
			   $this->metaftpd_devlog(1,'Erreur T3MakeNewFlexFormArray: no flexform field defined : '.serialize($flexconf),'meta_t3io');
 				 return false;
	  }
	  
		$myconf=$GLOBALS['TCA']['tt_content']['columns'][$flexconf['field']]['config'];
		$this->metaftpd_devlog(1,"My conf  :".serialize($myconf),'meta_t3io');  		
    $$flexArray=array();
		$flexDS=t3lib_BEfunc::getFlexFormDS($myconf,$row,'tt_content');
		
		$langChildren = $flexDS['meta']['langChildren'] ? 1 : 0;
    $langDisabled = $flexDS['meta']['langDisable'] ? 1 : 0;
 
    if ($langChildren || $langDisabled)     {
          $lKeys = array('DEF');
    } else {
    	    // hmm to be modified ...
          $lKeys = $editData['meta']['currentLangId'];
    }
		
		 if (is_array($flexDS['sheets']))       {
				$sKeys = array_keys($flexDS['sheets']);
     } else {
   			$sKeys = array('sDEF');
		}
    foreach($lKeys as $lKey) {
      foreach($sKeys as $sheet) {
         $sheetCfg = $flexDS['sheets'][$sheet];
          list ($dataStruct, $sheet) = t3lib_div::resolveSheetDefInDS($flexDS,$sheet);
 
             // Render sheet:
          if (is_array($dataStruct['ROOT']) && is_array($dataStruct['ROOT']['el'])) {
            		$lang = 'l'.$lKey;      // Separate language key
            		foreach($dataStruct['ROOT']['el'] as $el=>$val) {
          		$flexArray['data'][$sheet][$lang][$el]['v'.$lKey]='';
  					//$this->metaftpd_devlog(1,"Datastruct array  flexArray[$sheet][$lang][$el] :".serialize($dataStruct),'meta_t3io');  		
         	}
          } else {
          					$this->metaftpd_devlog(2,'Error T3MakeEmptyFlexFormArray:'.$data,'meta_t3io');
 										return 'Data Structure ERROR: No ROOT element found for sheet "'.$sheet.'".';
 									}
        }
    }
    
    // We copy eventually uploaded file to flexform field dir.
    $this->T3FlexFileCopy($res,$flexDS,$flexconf,$flexArray);
    return $flexArray;
 }

function T3ClearPageCache($PID) {
  //$this->metaftpd_devlog(2,'####  '.$this->BEUSER->isAdmin().' CLEAR PAGE CACHE ###### : '.$PID,'meta_t3io');
	$this->CFG->T3TCE->start(array(),array(),$this->BEUSER);
	$this->CFG->T3TCE->clear_cache('pages',$PID);  
}
	
function T3ClearAllCache() {
	$admin=$this->BEUSER->user['admin'];
	$this->BEUSER->user['admin']=1;
  //$this->metaftpd_devlog(2,'####  BE :  '.$this->BEUSER->isAdmin().' CLEAR PAGE CACHE ###### : '.$PID,'meta_t3io');
	$this->CFG->T3TCE->start(array(),array(),$this->BEUSER);
	$this->CFG->T3TCE->clear_cacheCmd('pages');  
	$this->BEUSER->user['admin']=$admin;
}
	
						//removeCacheFiles  (    )  ;

function metaftpd_devlog($level,$message,$ext,$func=array()) 
{
		if (in_array($level,$this->CFG->debuglevel) && (count($func)===0 || in_array($func,$this->CFG->debugfunction)))  t3lib_div::devlog($level.':'.$message ,$ext.($func?':'.$func:''));
}

	
// This functions extracts T3 info from filename to produce only the uploade file name : Ex "/a/b/c/test.doc" would become "test.doc"
// UnitTest : test_t3io_T3GetFileName

function T3GetFileName($path) {
	$pathArray=t3lib_div::trimexplode('/',$path);
	$c=count($pathArray);
	return $pathArray[$c-1];
}
				
function T3MakeVirtualPathFromPid($pid) {
	$rootline=t3lib_BEfunc::BEgetRootLine($pid); 
	//TO DO handle webmounts
	$this->metaftpd_devlog(1,'T3MakeVirtualPathFromPid :'.serialize($rootline),'meta_t3io','T3MakeVirtualPathFromPid');
	$virtualpath=$this->CFG->T3ROOTDIR.T3_FTPD_WWW_ROOT;
	foreach($rootline as $key=>$val) $virtualpath.="/".t3prefix.$key;
	return $virtualpath;
}

function T3FuncCopy($cmds) {
	$theFile = $cmds['data'];
	$theDest = $this->T3FILE->is_directory($cmds['target']);	// Clean up destination directory
	$altName = $cmds['altName'];
	if (!$theDest)	{			
		$this->T3FILE->writelog(2,2,100,'Destination "%s" was not a directory',Array($cmds['target']));
		$this->metaftpd_devlog(2,'Error : Destination was not a directory :'.$cmds['target'],'meta_t3io');
		return FALSE;
	}
	if (!$this->T3FILE->isPathValid($theFile) || !$this->T3FILE->isPathValid($theDest))	{
		$this->T3FILE->writelog(2,2,101,'Target or destination had invalid path (".." and "//" is not allowed in path). T="%s", D="%s"',Array($theFile,$theDest));
		$this->metaftpd_devlog(2,'Error : Target or destination had invalid path (".." and "//" is not allowed in path). T='.$theFile.', D='.$theDest,'meta_t3io');
		return FALSE;
	}
	// Processing of file or directory.
	$this->metaftpd_devlog(1,'**** '.$altName.' ******* T='.$theFile.', D='.$theDest,'meta_t3io');
	if (@is_file($theFile))	{	// If we are copying a file...
		if ($this->T3FILE->actionPerms['copyFile'])	{
			if (filesize($theFile) < ($this->T3FILE->maxCopyFileSize*1024))	{
				$fI = t3lib_div::split_fileref($theFile);
				if ($altName==1)	{	// If altName is set, we're allowed to create a new filename if the file already existed
					$theDestFile = $this->T3FILE->getUniqueName($fI['file'], $theDest);
					$fI = t3lib_div::split_fileref($theDestFile);
				} else {
					$theDestFile = $theDest.'/'.$fI['file'];
				}
		  		
	  		$this->metaftpd_devlog(1,'*********** T='.$theFile.', D='.$theDestFile,'meta_t3io');
				//if ($theDestFile && !@file_exists($theDestFile))	{
				
				if ($theDestFile)	{
					if ($this->T3FILE->checkIfAllowed($fI['fileext'], $theDest, $fI['file'])) {
						if ($this->T3FILE->PHPFileFunctions)	{
							copy ($theFile,$theDestFile);
						} else {
							$cmd = 'cp "'.$theFile.'" "'.$theDestFile.'"';
							exec($cmd);
						}
						clearstatcache();
						if (@is_file($theDestFile))	{
							$this->T3FILE->writelog(2,0,1,'File "%s" copied to "%s"',Array($theFile,$theDestFile));
							$this->metaftpd_devlog(1,'File : '.$theFile.', copied to '.$theDestFile,'meta_t3io');
							return $theDestFile;
						} else {
							$this->T3FILE->writelog(2,2,109,'File "%s" WAS NOT copied to "%s"! Write-permission problem?',Array($theFile,$theDestFile));
						  $this->metaftpd_devlog(2,'!!! ERROR File : '.$theFile.', was not copied to '.$theDestFile,'meta_t3io');
						}
					}
				} else 	$this->metaftpd_devlog(2,'*********** File exists T='.$theFile.', D='.$theDestFile,'meta_t3io');
			}
		}
	}
}
	
// File Copy to field ...	
	
function T3FileFieldCopy($fileinfo,$field,&$contentDataArray) {
	$cmds=array();
	$cmds['data']=$this->T3CleanFilePath($this->CFG->T3PHYSICALROOTDIR.$fileinfo['filepath']);
	$cmds['altName']=0;
	if ($fileinfo['cmd']=='insert') $cmds['altName']=1;
  $this->metaftpd_devlog(1,"====== T3FileFieldCopy , field: $field , fileinfo :".serialize($fileinfo),'meta_t3io','T3FileFieldCopy');
	if ($field) {
		$fieldCFG = $GLOBALS['TCA']['tt_content']['columns'][$field]['config'];
		// checksize must be implemented here !
		if ($fieldCFG['uploadfolder']) {
  		$this->metaftpd_devlog(1,"T3FileFieldCopy , fielddir: ".$fieldCFG['uploadfolder'],'meta_t3io','T3FileFieldCopy');
  		if ($field!='tx_metaftpd_ftpfile') $dir=$this->T3CleanFilePath($this->CFG->T3PHYSICALROOTDIR.'/'.$fieldCFG['uploadfolder']);
  		  else {
  		  	$dir=$this->T3CleanFilePath($this->CFG->T3PHYSICALROOTDIR.'/'.$fieldCFG['uploadfolder'].'/'.$fileinfo['pid']);
  				$direxists=is_dir($dir);
					// we create pid dir if it doesn't exist ...
  				if (!$direxists) {
  					$stat = mkdir($dir, 0777);
      			if (!$stat) {
  						$this->metaftpd_devlog(2,"!!! Error : T3FileFieldCopy, can't create ".$dir,'meta_t3io');
      			}
      		}
   		  	/*$dir=$this->T3CleanFilePath($this->CFG->T3PHYSICALROOTDIR.'/'.$fieldCFG['uploadfolder'].'/'.$fileinfo['pid'].'/'.$fileinfo['uid']);
   				$direxists=is_dir($dir);
					// we create pid dir if it doesn't exist ...
  				if (!$direxists) {
  					$stat = mkdir($dir, 0777);
      			if (!$stat) {
  						$this->metaftpd_devlog(2,"!!! Error : T3FileFieldCopy, can't create ".$dir,'meta_t3io');
      			}
      		}*/
    	}
			$cmds['target']=$dir;
			$this->T3FILE->start($cmds);
			$this->metaftpd_devlog(1,"T3FileFieldCopy:".$cmds['data'].' , '.$cmds['target'],'meta_t3io','T3FileFieldCopy');
			$name=$this->T3FuncCopy($cmds);
			if ($name) $contentDataArray[$field]=$this->T3GetFileName($name);
			$this->metaftpd_devlog(1,"T3FileFieldCopy file : ".$contentDataArray[$field].", name :".$name,'meta_t3io','T3FileFieldCopy');
		}
	}		
	$this->metaftpd_devlog(1,"======= T3FileFieldCopy end : ".$name,'meta_t3io','T3FileFieldCopy');
}

function T3FlexFileCopy($fileinfo,&$flexDS,$flexconf,&$flexArray) {
	$this->metaftpd_devlog(1,"========= T3FlexFileCopy Start , flex conf :".serialize($extConf['flex.']),'meta_t3io');  		
	$cmds=array();
	$cmds['data']=$this->T3CleanFilePath($this->CFG->T3PHYSICALROOTDIR.$fileinfo['filepath']);
	$cmds['altName']=0;
	if ($fileinfo['cmd']='insert') $cmds['altName']=1;
	$sheet=$flexconf['uploadFileSheet'];
	$field=$flexconf['uploadFileField'];
  $this->metaftpd_devlog(1,"T3FlexFileCopy , sheet : $sheet, field: $field",'meta_t3io');
	if ($sheet && $field) {
		$this->metaftpd_devlog(4,"T3FlexFileCopy field conf  :".serialize($flexDS['sheets'][$sheet]['ROOT']['el'][$field]),'meta_t3io');  		
		$fieldCFG = $flexDS['sheets'][$sheet]['ROOT']['el'][$field]['TCEforms']['config'];
		// checksize must be implemented here !
		if ($fieldCFG['uploadfolder']) {
   		$dir=$this->CFG->T3PHYSICALROOTDIR.$fieldCFG['uploadfolder']; // .'/'.$fileinfo['pid'];
  		$direxists=is_dir($dir);
			// we create pid dir if it doesn't exist ...
  		if (!$direxists) {
  			$stat = mkdir($dir, 0777);
      	if (!$stat) {
  				$this->metaftpd_devlog(2,"!!! Error : T3FileFieldCopy, can't create ".$dir,'meta_t3io');
      	}
      }
      
 		  $this->metaftpd_devlog(4,"T3FlexFileCopy , sheet : $sheet, field: $field",'meta_t3io');
			$cmds['target']=$dir;
			$this->T3FILE->start($cmds);
			$this->metaftpd_devlog(1,"T3FlexFileCopy:".$cmds['data'].' , '.$cmds['target'],'meta_t3io');
			$name=$this->T3FuncCopy($cmds);
			if ($name) $flexArray['data'][$sheet]['lDEF'][$field]['vDEF']=$this->T3GetFileName($name);
			$this->metaftpd_devlog(1,"T3FlexFileCopy name :".$name,'meta_t3io');
		}
	}		
}	

// Creation/Edition of uploaded content
// 3 cases :
// 1) New upload :
//		- File is uploaded to temp dir, then copied to /uploads/tx_metaftpd/<pid> dir, file is eventually copied to other fields for display (flexform, ...), database image is created 
// 2) Update of file (we know it from filename (contains uid & content type)..
//  	- File is directly updated in /uploads/tx_metaftpd/<pid>, file is eventually copied to other fields for display (flexform, ...), database image is created 


function T3LinkFileUpload($fileinfo) {
	$this->metaftpd_devlog(1,"====== T3LinkFileUpload:".serialize($fileinfo),'meta_t3io');
		
	// We construct data array and make file copies
		
	$contentDataArray=$this->T3GetCTypeFile($fileinfo);
	$this->metaftpd_devlog(4,"T3LinkFileUpload data:".serialize($contentDataArray),'meta_t3io');
	
	// We insert or update info according to fileinfo array
		
	if ($fileinfo['cmd']=='update') {
		$this->metaftpd_devlog(3,"T3LinkFileUpload Upload sql:".$this->CFG->T3DB->UPDATEquery('tt_content',"uid='".$fileinfo['uid']."'",$contentDataArray),'meta_t3io');
		$this->CFG->T3DB->exec_UPDATEquery('tt_content',"uid='".$fileinfo['uid']."'",$contentDataArray);
	} else {
		$this->metaftpd_devlog(3,"T3LinkFileUpload Insert sql:".$this->CFG->T3DB->INSERTquery('tt_content',$contentDataArray),'meta_t3io');
		$this->CFG->T3DB->exec_INSERTquery('tt_content',$contentDataArray);
	}
  
  // We clear page cache on modification
  
	$this->T3ClearPageCache($fileinfo['pid']);
	$this->metaftpd_devlog(1,"====== T3LinkFileUpload Fin.",'meta_t3io');
	return $contentDataArray;
}
	
	
function T3GetFileMount($name) 
{
	$ret=array();
	//echo "T3GetFileMount[ $name ]getFM";
	$filemounts=$this->BEUSER->groupData['filemounts'];
	$this->metaftpd_devlog(1,"=== T3GetFileMount start :".serialize($filemounts),'meta_t3io','T3GetFileMount');
	if(is_array($filemounts))
	{
		foreach($filemounts as $fm) {
			if ($fm['name']==$name) {
				$path=substr($fm['path'],strlen($this->CFG->T3PHYSICALROOTDIR));
				$fm['relPath']=$path;
				$this->metaftpd_devlog(1,"=== T3GetFileMount end".serialize($fm),'meta_t3io','T3GetFileMount');
				return $fm;
			}
		}
	}
	$this->metaftpd_devlog(1,"=== T3GetFileMount end".serialize($ret),'meta_t3io','T3GetFileMount');
	return $ret;
}
	
	
// PATH is composed of siteroot/T3_FTPD_FILE_ROOT/mountpoint/ ...
// replaces taht part with mount point path
// Test case :
// /FILEMOUNT => 
// /WEBMOUNT	=> should never be called ...

function T3ReplaceMountPointsByPath($path) 
{
	$ret = '';
	
	// we take out site root
	$l=strlen($path);
	$this->metaftpd_devlog(1,"=== T3ReplaceMountPointsByPath start: $path rootdir: ".$this->CFG->T3ROOTDIR."physicalrootdir :".$this->CFG->T3PHYSICALROOTDIR,'meta_t3io','T3ReplaceMountPointsByPath');

	
	$path=str_replace($this->CFG->T3ROOTDIR,'',$path);
	$path='/'.str_replace($this->CFG->T3PHYSICALROOTDIR,'',$path);
	$path=$this->T3CleanFilePath($path);
	$this->metaftpd_devlog(1,"====== T3ReplaceMountPointsByPath intermediate :".$path,'meta_t3io','T3ReplaceMountPointsByPath');
	$l2=strlen($path);
	$rootflag=0;
	// we check if we must add root ...
	if ($l2!=$l) $rootflag=1;
	$parr=t3lib_div::trimexplode('/',$path);
	$c=count($parr);
	// ! relative path
	
	if (substr($path, 0, 1) == "/" && $c >=3 ) {
		$this->metaftpd_devlog(1,"====== T3ReplaceMountPointsByPath p1 $parr[1] ; p2 $parr[2] path : $path , rep:".$ret,'meta_t3io','T3ReplaceMountPointsByPath');
		if ($parr[1]==T3_FTPD_FILE_ROOT) {
			$fm=$this->T3GetFileMount($parr[2]);
			if (count($fm)) {
				$rp=$fm['relPath'];
				$ret=str_replace('/'.T3_FTPD_FILE_ROOT.'/'.$parr[2],$rp,$path);
				//$ret=$fm['path'];
			  $this->metaftpd_devlog(1,"====== T3ReplaceMountPointsByPath filemount $parr[2] ; $rp ; path : $path , rep:".$ret,'meta_t3io','T3ReplaceMountPointsByPath');
			} else {
			  $this->metaftpd_devlog(1,"====== T3ReplaceMountPointsByPath no filemounts ! $parr[2] ; $rp ; path : $path , rep:".$ret,'meta_t3io','T3ReplaceMountPointsByPath');				
				$ret=str_replace(T3_FTPD_FILE_ROOT,'',$path);
			}
		} else {
			$this->metaftpd_devlog(1,"====== T3ReplaceMountPointsByPath: no file root  path $path".$c.$parr[1],'meta_t3io','T3ReplaceMountPointsByPath');
			$ret=$path;
		}
	} else {
		// relative path
		$this->metaftpd_devlog(1,"====== T3ReplaceMountPointsByPath relpath $path c:  $c p1: $parr[1]",'meta_t3io','T3ReplaceMountPointsByPath');
		if ($parr[1]==T3_FTPD_FILE_ROOT && $c >=2) 
		{			
			// TODO: Bugfix needed here
			$fm=$this->T3GetFileMount($parr[1]);
			
			if (count($fm)) 
			{
				$rp=$fm['relPath'];
				$ret=str_replace(T3_FTPD_FILE_ROOT.'/'.$parr[1],$rp,$path);
			} 
			else
			{
				$ret=str_replace(T3_FTPD_FILE_ROOT,'',$path);
			}
		} else {
			$this->metaftpd_devlog(1,"====== T3ReplaceMountPointsByPath no file root 2 : path $path c: $c p1: $parr[1]",'meta_t3io','T3ReplaceMountPointsByPath');
			$ret=$path;
		}
	}
	
	$this->metaftpd_devlog(1,"T3ReplaceMountPointsByPath3 : rootflag : $rootflag : ret : $ret",'meta_t3io','T3ReplaceMountPointsByPath');

	if ($rootflag) $ret=$this->CFG->T3PHYSICALROOTDIR.(str_replace($this->CFG->T3PHYSICALROOTDIR,'',$ret));
	$ret=$this->T3CleanFilePath($ret);
	$this->metaftpd_devlog(1,"=== T3ReplaceMountPointsByPath3 end rootflag : $rootflag:".$ret,'meta_t3io','T3ReplaceMountPointsByPath');
		
	return $ret;
}
	
// Replaces file mounts ...	
// not used ???
	
function T3ReplacePathByMountPoints($path) {
	$parr=t3lib_div::trimexplode('/',$path);
	$c=count($parr);
	if (substr($path, 0, 1) == "/" && $c >=3 ) {
		if ($parr[1]==T3_FTPD_FILE_ROOT) {
			$fm=$this->T3GetFileMount($parr[2]);
			$rp=$fm['relPath'];
			$ret=str_replace($path,'/'.T3_FTPD_FILE_ROOT.'/'.$parr[2],$rp);
		}
	} else {
		if ($parr[0]==T3_FTPD_FILE_ROOT && $c >=2) {
			$fm=$this->T3GetFileMount($parr[1]);
			$rp=$fm['relPath'];
			$ret=str_replace($path,'/'.T3_FTPD_FILE_ROOT.'/'.$parr[1],$rp);
		}
	}
	return $ret;		
}


// Cleans filepath 
// removes all ... for security reaons
// replaces all // by /
// Trims white spaces before and  after
// UnitTest : test_T3CleanFilePath

function T3CleanFilePath($filepath) {
	$this->metaftpd_devlog(1,"T3CleanFilePath start:".$filepath,'meta_t3io','T3CleanFilePath');
	$filepath=trim($filepath);

	// security ...
	while (strpos($filepath,'..')!==false) {
		$filepath=str_replace('..','',$filepath);
  }

	while (strpos($filepath,'//')!==false) {
		$filepath=str_replace('//','/',$filepath);
  }
	$this->metaftpd_devlog(1,"T3CleanFilePath end:".$filepath,'meta_t3io','T3CleanFilePath');
  return $filepath;
}

// Builds physical path from virtual path ....
// UnitTest : test_T3MakeFilePath
// TODO: BUGFIX needed here!!!
function T3MakeFilePath($virtualpath) 
{
	$virtualpath=$this->T3CleanFilePath($virtualpath);
	$this->metaftpd_devlog(6,"=== T3MakeFilePath start:".$virtualpath,'meta_t3io','T3MakeFilePath');

	// We build relative path if necessary

	if ($virtualpath.'/'==$this->CFG->T3PHYSICALROOTDIR) 
	{
		$relvirtualpath='/';
	} 
	else 
	{
		$relvirtualpath='/'.str_replace($this->CFG->T3PHYSICALROOTDIR,'',$virtualpath);
	}

	if (substr($relvirtualpath, 0, 1) == "/") 
	{
		$physicalpath= $this->CFG->T3PHYSICALROOTDIR . $relvirtualpath;
	} 
	else 
	{
		// this is not good ...
		$physicalpath= $this->CFG->T3PHYSICALROOTDIR . $this->cwd . $relvirtualpath;
	}
	$physicalpath=$this->T3CleanFilePath($physicalpath);
	$this->metaftpd_devlog(6,"====== T3MakeFilePath physicalpath before mount replace :".$physicalpath,'meta_t3io','T3MakeFilePath');
	$physicalpath=$this->T3ReplaceMountPointsByPath($physicalpath);
	$physicalpath=$this->T3CleanFilePath($physicalpath);
	$this->metaftpd_devlog(6,"=== T3MakeFilePath end :".$physicalpath,'meta_t3io','T3MakeFilePath');
	return $physicalpath;
}

/* very important function for security must be implemented before stable version */
// CHECKS $path is a valid path

function T3FileExists($path) {
	$this->metaftpd_devlog(5,"T3FileExists start:".$path,'meta_t3io','T3FileExists');
	$ret=false;
	$path=$this->T3CleanFilePath($path);
	$fileinfo=$this->T3IsFile($path);
	$this->metaftpd_devlog(1,"T3FileExists: fileinfo : ".serialize($fileinfo),'meta_t3io','T3FileExists');

	if ($fileinfo['isWebmount']) {
		// If first level WEBMOUNT...
		if ($fileinfo['level']==1) return true;
		$table='pages';

		if ($fileinfo['isWebcontent']) $table='tt_content';

		$enable=$GLOBALS['TSFE']->sys_page->enableFields($table,-1,array('fe_group'=>1));

		// Must add check  web mounts here !!!
		if ($fileinfo['level']==2)	 {
			$webmounts=$this->BEUSER->groupData['webmounts'];
			$this->metaftpd_devlog(1,"============ T3FileExists webmounts :".$webmounts,'meta_t3io','T3FileExists');
			//$parr[]=array();
			if (strlen(trim($webmounts))>0) {
				$pids=t3lib_div::trimexplode(',',$webmounts);
				foreach($pids as $pid) {
					$this->metaftpd_devlog(1,"============ T3FileExists webmounts pid:".$pid,'meta_t3io','T3FileExists');
					//$parr[]=$this->CFG->T3PAGE->getPage($pid);
					if ($pid==$fileinfo['uid'] && $fileinfo['pid']==0) {
						$this->metaftpd_devlog(1,"T3FileExists path $path fp $filepath exit: true",'meta_t3io','T3FileExists');
						return true;
					}
				}
			}
		}

		// checks filename if uid=0 ...
		$this->T3GetUidFromFileName($fileinfo);

		$res=$this->CFG->T3DB->exec_SELECTquery('uid',$table,'pid='.intval($fileinfo['pid']).' AND uid='.intval($fileinfo['uid']).$enable );
		$this->metaftpd_devlog(1,"T3FileExists:".$this->CFG->T3DB->SELECTquery('*',$table,'pid='.intval($fileinfo['pid']).' AND uid='.intval($fileinfo['uid']).$enable ),'meta_t3io');
		if ($res) {
			while ($row=$this->CFG->T3DB->sql_fetch_assoc($res)) {
				$ret=true;
				break;
			}
		} else {
			$this->metaftpd_devlog(1,"T3FileExists: pb db","meta_t3io",'T3FileExists');
		}
	}
	else
	{
		// File mount

		$filepath=$this->T3MakeFilePath($path);
		$ret=file_exists($filepath);

	}
	$this->metaftpd_devlog(5,"T3FileExists end ; path $path fp $filepath exit:".$ret,'meta_t3io','T3FileExists');
	return $ret;
}

// get creation time of path ressource
// param 1 : virtualpath given by webdav browser
// returns : ctime of ressource, if ressource is not valid returns 0...

function T3FileCTime($virtualpath) {
	$this->metaftpd_devlog(1,"============ T3FileCTime start: $T3FileCTime ",'meta_t3io','T3FileCTime');
	$ctime=0;
	$virtualpath=$this->T3CleanFilePath($virtualpath);
	$fileinfo=$this->T3IsFile($virtualpath);
  if ($fileinfo['isWebmount']) {
		if (!$fileinfo['isT3File'] ) $ret=$fileinfo['pcdate'];
		if ($fileinfo['isT3File'] && $fileinfo['isWebcontent']) $ctime=$fileinfo['cdate'];

	}
	else
	{
		$ctime=filectime($this->T3MakeFilePath($virtualpath));
	}
	$this->metaftpd_devlog(1,"============ T3FileCTime end ctime : $ctime ",'meta_t3io','T3FileCTime');
	return $ctime;
}

function T3FileCTimeI($virtualpath,$fileinfo) {
	$this->metaftpd_devlog(1,"============ T3FileCTimeI start: $T3FileCTime ",'meta_t3io','T3FileCTime');
	$ctime=0;
  if ($fileinfo['isWebmount']) {
		if (!$fileinfo['isT3File'] ) $ret=$fileinfo['pcdate'];
		if ($fileinfo['isT3File'] && $fileinfo['isWebcontent']) $ctime=$fileinfo['cdate'];

	}
	else
	{
		$ctime=filectime($this->T3MakeFilePath($virtualpath));
	}
	$this->metaftpd_devlog(1,"============ T3FileCTimeI end ctime : $ctime ",'meta_t3io','T3FileCTime');
	return $ctime;
}

function T3FileSize($path) {
	$this->metaftpd_devlog(1,"============ T3FileSize start: $path ",'meta_t3io','T3FileSize');
  $ret=0;
	$path=$this->T3CleanFilePath($path);
	$fileinfo=$this->T3IsFile($path);
  if ($fileinfo['isWebmount']) {
		if ($fileinfo['isT3File']) $ret=$fileinfo['size'];
		$this->metaftpd_devlog(1,"============ T3FileSize start: $path : $ret ",'meta_t3io','T3FileSize');
	}
	else
	{
		$ret=filesize($this->T3MakeFilePath($path));
		$this->metaftpd_devlog(1,"============ T3FileSize start: $path : $ret ",'meta_t3io','T3FileSize');
	}
	$this->metaftpd_devlog(1,"============ T3FileSize end: $ret ",'meta_t3io','T3FileSize');
	return $ret;
}

// get modifcation time of file ...

function T3FileMTime($path) {
	$this->metaftpd_devlog(1,"============ T3FileMTime start: $path ",'meta_t3io','T3FileMTime');
	$ret=0;
	$path=$this->T3CleanFilePath($path);
	$fileinfo=$this->T3IsFile($path);
  if ($fileinfo['isWebmount']) {
		if (!$fileinfo['isT3File']) $ret=$fileinfo['pmdate'];
		if ($fileinfo['isT3File'] && $fileinfo['isWebcontent']) $ret=$fileinfo['mdate'];
	}
	else
	{
		$ret=filemtime($this->T3MakeFilePath($path));
	}
	$this->metaftpd_devlog(1,"============ T3FileMTime end: $ret ",'meta_t3io','T3FileMTime');
	return $ret;
}

function T3FileMTimeI($path,$fileinfo) {
	$this->metaftpd_devlog(1,"============ T3FileMTimeI start: $path ",'meta_t3io','T3FileMTime');
	$ret=0;
  if ($fileinfo['isWebmount']) {
		if (!$fileinfo['isT3File']) $ret=$fileinfo['pmdate'];
		if ($fileinfo['isT3File'] && $fileinfo['isWebcontent']) $ret=$fileinfo['mdate'];
	}
	else
	{
		$ret=filemtime($this->T3MakeFilePath($path));
	}
	$this->metaftpd_devlog(1,"============ T3FileMTimeI end: $ret ",'meta_t3io','T3FileMTime');
	return $ret;
}


function T3IsDir($path) 
{
	$this->metaftpd_devlog(1,"============ T3IsDir start: $path ",'meta_t3io','T3IsDir');
	
	$ret=false;
	$path=$this->T3CleanFilePath($path);
	
	$fileinfo=$this->T3IsFile($path);
	
	if ($fileinfo['isWebmount']) 
	{
		if (!$fileinfo['isT3File']) $ret=true;
		//if ($fileinfo['isDir']) $ret=true;
	}
	else
	{
//		$this->metaftpd_devlog(1,"T3isDir a : $path ,***".$this->T3MakeFilePath($path),'meta_t3io','T3IsDir');
		$ret=is_dir($this->T3MakeFilePath($path));
	}
	//$this->metaftpd_devlog(4,"T3IsDir :".$path." : ".$ret,'meta_t3io');
	$this->metaftpd_devlog(1,"============ T3IsDir end: $ret ",'meta_t3io','T3IsDir');
	return $ret;
}

// lists files of Filemount or Webmount
// parameter 1 virtualpath

function T3ListDir($path) 
{
	$this->metaftpd_devlog(1,'T3ListDir->$path'.$path,basename(__FILE__).':'.__LINE__,'T3ListDir');
	$list=array();
	
	$fileinfo=$this->isT3($path);
	$this->metaftpd_devlog(1,'T3ListDir->$fileinfo'.print_r($fileinfo, 1),basename(__FILE__).':'.__LINE__,'T3ListDir');
	
	// Is Path a webmount ?			
	if ($fileinfo['isWebmount']) 
	{
		// level 1 is choice between file mounts and web mounts
		if ($fileinfo['level']==1) 
		{
			// Level one we get the webmounts
			$webmounts=$this->BEUSER->groupData['webmounts'];
			$this->metaftpd_devlog(1,'T3ListDir->$webmounts'.print_r($webmounts,1),basename(__FILE__).':'.__LINE__,'T3ListDir');
			
			//$parr[]=array();
			if (strlen(trim($webmounts))>0) 
			{
				$pids=t3lib_div::trimexplode(',',$webmounts);
				foreach($pids as $pid) 
				{
					$this->metaftpd_devlog(1,'T3ListDir->$pid'.print_r($pid,1),basename(__FILE__).':'.__LINE__,'T3ListDir');
					if ($pid==0) 
					{
						$parr[]=array('uid'=>0, 'title'=>'Root');
				  	} 
				  	else
				  	{
				  		$parr[]=$this->CFG->T3PAGE->getPage($pid);
				  	}
				}
			}
		} 
		else 
		{
			// Level one we ask for page menu 
			$pid=$fileinfo['pid'];
			//$pid=$fileinfo['rootline'][$fileinfo['level']];
			$this->metaftpd_devlog(3,"T3ListDir : pid ".$pid,'meta_t3io','T3ListDir');			
			$parr=$this->CFG->T3PAGE->getMenu($pid);
		}
		
		// T3 Pages
		//$this->metaftpd_devlog(1,"============ T3ListDir webmounts :".serialize($parr),'meta_t3io','T3ListDir');
		if (is_array($parr))
		{
			foreach($parr as $pid=>$row) 
			{
				$list[] =  $this->T3MakePageTitle($row);
			}
		}

		$enable=$GLOBALS['TSFE']->sys_page->enableFields('tt_content',-1,array('fe_group'=>1));
		$orderby=' ORDER BY sorting ';
		
		// must add different data types here !!
		$res=$this->CFG->T3DB->exec_SELECTquery('uid,ctype,header,bodytext,tx_metaftpd_ftpfile','tt_content','pid='.intval($fileinfo['pid']).' '.$enable.$orderby );
		if ($res) 
		{
			while ($row=$this->CFG->T3DB->sql_fetch_assoc($res)) 
			{
				$this->metaftpd_devlog(4,"tt_content :".$row['uid'].'['.str_replace('/','',$row['ctype']).'].'.str_replace('/','',$row['ctype']),'meta_t3io','T3ListDir');
				$title=$this->T3MakeContentTitle($row);
				$list[] = $title?$title:$this->T3MakeContentTitle($row,1,'[unknown]'); 
				//$list[] = $row['tx_metaftpd_ftpfile'];				
			}
		} 
		else 
		{
			$this->metaftpd_devlog(2,"T3ListDir : PB DB",'meta_t3io','T3ListDir');
		}
	} 
	else 
	{
		$this->metaftpd_devlog(1,"============ T3ListDir file mounts :$path",'meta_t3io','T3ListDir');
		
		// We handle File mounts here
		if ($path=='/') 
		{ // We are at root of FTPD/WebDAV server. We present choice between filemounts and webmounts
			$list[] = T3_FTPD_FILE_ROOT;
			$list[] = T3_FTPD_WWW_ROOT;
			$this->metaftpd_devlog(1,"============ T3ListDir level 1 :".serialize($list),'meta_t3io','T3ListDir');
		} 
		elseif ($path=='/'.T3_FTPD_FILE_ROOT.'/') 
		{
			$this->metaftpd_devlog(1,'$this->BEUSER: '.print_r($this->BEUSER, 1),basename(__FILE__).':'.__LINE__,'T3ListDir');
			
			$filemounts=$this->BEUSER->groupData['filemounts'];
			$this->metaftpd_devlog(1,"============ T3ListDir filemounts 2:".serialize($filemounts),'meta_t3io','T3ListDir');
			foreach($filemounts as $fm) 
			{
				$this->metaftpd_devlog(4,"filemount :".$fm['name'],'meta_t3io','T3ListDir');
				$filename=$this->CFG->T3ROOTDIR.substr($fm['path'],strlen($this->CFG->T3ROOTDIR));
				$list[] = $fm['name'];
			}
		} 
		else 
		{
			$this->metaftpd_devlog(1,"T3ListDir : path : $path",'meta_t3io','T3ListDir');
			
			$path=$this->T3ReplaceMountPointsByPath($path);
			
			$this->metaftpd_devlog(1,"T3ListDir : path : $path",'meta_t3io','T3ListDir');
			
			$dir=$this->CFG->T3PHYSICALROOTDIR . str_replace($this->CFG->T3PHYSICALROOTDIR,'',$path);
			
			$this->metaftpd_devlog(1,"T3ListDir : dir : $dir",'meta_t3io','T3ListDir');
			
			if ($handle = @opendir($dir))
			{
				while (false !== ($file = readdir($handle))) 
				{
					if ($file == "." || $file == "..") continue;
					$list[] = $file;
				}
				
				if (!$handle)	$this->metaftpd_devlog(2,"T3ListDir erreur 1 ***********:".$path,'meta_t3io');
				
				closedir($handle);
			} 
			else 
			{
				$this->metaftpd_devlog(2,"T3ListDir erreur 2 ************ :".$path,'meta_t3io');
				return false;
			}
		}			
	}
	$this->metaftpd_devlog(1,"T3ListDir fin:".$path." list:".serialize($list),'meta_t3io','T3ListDir');
	return $list;	
 }

// function to clean html tags ...

function T3_strip_selected_tags($text, $tags = array())
{
   $this->metaftpd_devlog(3,"T3_strip_selected_tags: ".serialize($tags),'meta_t3io');
   $args = func_get_args();
   $text = array_shift($args);
   $tags = func_num_args() > 2 ? array_diff($args,array($text))  : (array)$tags;
   foreach ($tags as $tag){
       if(preg_match_all('/<'.$tag.'[^>]*>((\\n|\\r|.)*)<\/'. $tag .'>/iu', $text, $found)){
           $text = str_replace($found[0],$found[1],$text);
       }
   }

   return preg_replace('/(<('.join('|',$tags).')(\\n|\\r|.)*\/>)/iu', '', $text);
}
   

function T3StripComments($document){
		$search = array('@<script[^>]*?>.*?</script>@si',  // Strip out javascript
               '@<style[^>]*?>.*?</style>@siU',    // Strip style tags properly
               '@<![\s\S]*?--[ \t\n\r]*>@',
               '@<!DOCTYPE[\s\S]*?[ \t\n\r]*>@'         // Strip multi-line comments including CDATA
				);
		$text = preg_replace($search, '', $document);
return $text;
}

}
	if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/meta_ftpd/class.tx_metaftpd_t3io.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/meta_ftpd/class.tx_metaftpd_t3io.php']);
}