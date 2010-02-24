<?php
/*****************************************/
/*
STANDS AS A SAMPLE (NOT TESTED, HAS TO BE ADAPTED TO YOUR NEEDS)
*/
/*****************************************/
class InviterController extends AppController {

	var $name = 'Inviter';

	var $uses = array();

	var $helpers = array('Html', 'Form', 'Session');
	var $components = array('RequestHandler', 'Openinviter'); 

	function beforeFilter(){}

	function beforeRender(){}

	function index(){
		if($this->Openinviter->checkInstall()){
			//deleting previous oi session id and contacts
			if($this->Session->check('OpenInviter')){
				$oi_session = $this->Session->read('OpenInviter');
				if(isset($oi_session['session'])&&isset($this->Openinviter->cookiespath)){
					if(file_exists($this->Openinviter->cookiespath.DS.'oi.'.$oi_session['session'].'.cookie')){
						unlink($this->Openinviter->cookiespath.DS.'oi.'.$oi_session['session'].'.cookie');
					}
				}
				$this->Session->delete('OpenInviter');
			}

			//get plugins list
			$plugins = $this->Openinviter->getPlugins();

			if($plugins===false){
				$this->set('plugins', false);
			}else{
				$this->set('plugins', $plugins);
			}
		}else{
			$this->set('plugins', false);
		}
	}

	function invite($type = null){
		if(isset($type)&&($type=='email'||$type=='social')){
			if($this->Session->check('OpenInviter')){
				$oi_session = $this->Session->read('OpenInviter');
				if(isset($oi_session['contacts'])&&isset($oi_session['availablePlugins'])&&is_array($oi_session['availablePlugins'])&&count($oi_session['availablePlugins'])>0&&isset($oi_session['availablePlugins'][$oi_session['plugin']])){
					$this->Openinviter->availablePlugins = $oi_session['availablePlugins'];
					if(isset($oi_session['plugin'])&&isset($oi_session['session'])&&$this->Openinviter->startPlugin($oi_session['plugin'])){
						/*
							$message is the message you want to send : array('body' => ?,'subject' => ?, 'attachment').
							!!!!WARNING : avoid to invite contacts from social networks like facebook, they can ban the user for spam fraud : WARNING!!!
							$recipients is an array of imported and selected ids (for social websites) or emails (for email websites) from the third party website.
						*/
						//Try to send a message from the third party website api/interface
						//$sent = $this->Openinviter->sendMessage($oi_session['session'],$message,$recipients);
						/*if($sent===false){
							//plugin not working
						}else{
							if($sent==-1){
								//no website api/interface, mail instead
								//send email here
								//success
								//deleting oi session id and contacts
								if($this->Session->check('OpenInviter')){
									$oi_session = $this->Session->read('OpenInviter');
									if(isset($oi_session['session'])&&isset($this->Openinviter->cookiespath)){
										if(file_exists($this->Openinviter->cookiespath.DS.'oi.'.$oi_session['session'].'.cookie')){
											unlink($this->Openinviter->cookiespath.DS.'oi.'.$oi_session['session'].'.cookie');
										}
									}
									$this->Session->delete('OpenInviter');
								}
								$this->Openinviter->logout();
							}else{
								//success
							}
						}*/
					}else{
						$this->redirect('/inviter/index/');
						exit();
					}
				}else{
					$this->redirect('/inviter/index/');
					exit();
				}
			}else{
				$this->redirect('/inviter/index/');
				exit();
			}
		}else{
			$this->redirect('/inviter/index/');
			exit();
		}
	}

	function import(){
		if($this->Openinviter->checkInstall()){
			/******** your turn now ! ********/
			//use $this->__importContacts($plugin,$username,$userpass) method (has quite explicit args)
			//$plugin is the selected plugin name from the list in index() method
		}else{
			$this->redirect('/inviter/index/');
			exit();
		}
	}

	function __importContacts($plugin = null,$username = null,$userpass = null){
		if(isset($plugin)&&isset($username)&&isset($userpass)){
			if($this->Openinviter->startPlugin($plugin)){
				if($this->Openinviter->login(html_entity_decode(rawurldecode($username),ENT_QUOTES,'UTF-8'),html_entity_decode(rawurldecode($userpass),ENT_QUOTES,'UTF-8'))){
					$contacts = $this->Openinviter->getMyContacts();
					if(is_array($contacts)&&count($contacts)>0){
						$oi_session_id = $this->Openinviter->getSessionID();
						if($oi_session_id===false){
							$this->Openinviter->logout();
							return false;
						}else{
							//deleting previous oi session id and contacts
							if($this->Session->check('OpenInviter')){
								$oi_session = $this->Session->read('OpenInviter');
								if(isset($oi_session['session'])&&isset($this->Openinviter->cookiespath)){
									if(file_exists($this->Openinviter->cookiespath.DS.'oi.'.$oi_session['session'].'.cookie')){
										unlink($this->Openinviter->cookiespath.DS.'oi.'.$oi_session['session'].'.cookie');
									}
								}
								$this->Session->delete('OpenInviter');
							}
							//saving oi session id and contacts
							$this->Session->write('OpenInviter', array(
								'plugin' => $plugin,
								'contacts' => $contacts,
								'session' => $oi_session_id,
								'availablePlugins' => $this->Openinviter->availablePlugins,
							));
							//returning contacts
							return $contacts;
						}
					}else{
						//no contacts
						$this->Openinviter->logout();
						return array();
					}
				}else{
					//login error
					return array('error' => array('Inviter' => array('username' => array('Incorrect' => 1),'userpass' =>  array('Incorrect' => 1))));
				}
			}else{
				//plugin error
				return array('Inviter' => array('plugins_email' => array('Incorrect' => 1),'plugins_social' => array('Incorrect' => 1)));
			}
		}else{
			return false;
		}
	}

	/*
		userful test for email addresses
	*/
	function __isEmail($string){
		return eregi("^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$", $string);
	}

	/*
		userful to convert unicode text to utf8
	*/
	function __unicodeDecode($string = null){
		if(isset($string)){
			$replacement = '&#x\\1;';
			$pattern = '#%u([0-9a-f]{3,4})#i';

			$string = preg_replace($pattern, $replacement, urldecode($string));

			$pattern = '#\\\u([0-9a-f]{3,4})#i';
			$string = preg_replace($pattern, $replacement, urldecode($string));

			return utf8_decode(stripslashes(html_entity_decode($string, ENT_QUOTES, 'UTF-8')));
		}else{
			return null;
		}
	}
}
?>