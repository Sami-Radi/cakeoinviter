<?php
class OpeninviterComponent extends Object{

	public $configOK = true;

	public $cookiespath = null;
	public $availablePlugins = null;

	private $version = '1.8.1';
	private $controller = false;
	private $transport = 'curl';

	private $oipath = null;
	private $confpath = null;
	private $pluginspath = null;

	public function startup(&$controller){
		$this->controller = $controller;

		$this->confpath = TMP.'oiconf';
		$this->cookiespath = TMP.'cookies';
		$this->oipath = VENDORS.'OpenInviter';
		$this->pluginspath = $this->oipath.DS.'plugins';

		$this->pluginpath = DS.'OpenInviter'.DS.'plugins';
		$this->basepath = $this->pluginpath.DS.'_base';

		vendor($this->basepath);
	}

	public function checkInstall(){
		//checking php version
		if(version_compare(PHP_VERSION,'5.0.0','<')){
			return false;
		}else{
			//checking extensions
			if(extension_loaded('dom')&&class_exists('DOMDocument')){
				//checking transport method
				$transport = 'curl';
				if(extension_loaded('curl')&&function_exists('curl_init')){
					$curl = true;
				}else{
					$curl = false;
					$transport = 'wget';
					passthru("wget --version",$wget);
					$wget = (($wget==0) ? true : false);
				}
				if($curl||$wget){
					if($this->transport!=$transport){
						$this->transport = $transport;
					}
					//checking cookie path
					if(is_writable($this->cookiespath)){
						$cookie_files = glob($this->cookiespath.DS.'*.cookie');
						foreach($cookie_files as $cookie_file){
							if(is_file($cookie_file)&&file_exists($cookie_file)&&(time() > filemtime($cookie_file) + 86400)){
								unlink($cookie_file);
							}
						}
						return true;
					}else{
						return false;
					}
				}else{
					return false;
				}
			}else{
				return false;
			}
		}
	}

	/**
	* Disabled
	*/
	private function checkConfig(){
		return true;
	}

	/**
	* Disabled
	*/
	public function statsQuery($query){
		return false;
	}

	/**
	* Disabled
	*/
	private function statsCheck(){
		return true;
	}

	/**
	* Disabled
	*/
	private function statsOpenDB(){
		return true;
	}

	/**
	* Disabled
	*/
	private function statsRecordImport($contacts){
		return true;
	}

	/**
	* Disabled
	*/
	private function statsRecordMessages($msg_type,$messages){
		return true;
	}

	/**
	* Find out the version of OpenInviter
	* 
	* Find out the version of the OpenInviter base class
	* 
	* @return string The version of the OpenInviter base class.
	*/
	public function getVersion(){
		return $this->version;
	}

	/**
	* get the installed plugins
	* 
	* returns information about the available plugins
	* 
	* @return mixed An array of the plugins available or FALSE if there are no plugins available.
	*/
	public function getPlugins($update = false){
		$files = array();
		$plugins = array();
		$temp = glob($this->pluginspath.DS."*.plg.php");

		foreach ($temp as $file){
			if(strpos($file,'.plg.php')!==false){
				$files[basename($file,'.plg.php')] = $file;
			}
		}

		if(count($files)>0){
			ksort($files);
			foreach($files as $plugin => $file){
				if(file_exists($this->confpath.DS.$plugin.'.conf')){
					include_once($this->confpath.DS.$plugin.'.conf');
					if($enable&&$update==false){
						include($file);
						if(isset($_pluginInfo)&&$this->checkVersion($_pluginInfo['base_version'])){
							$plugins[$_pluginInfo['type']][$plugin] = $_pluginInfo;
						}
					}else{
						include($file);
						if(isset($_pluginInfo)&&$this->checkVersion($_pluginInfo['base_version'])){
							$plugins[$_pluginInfo['type']][$plugin] = array_merge(array('autoupdate' => $autoUpdate), $_pluginInfo);
						}
					}
				}else{
					include($file);
					if(isset($_pluginInfo)&&$this->checkVersion($_pluginInfo['base_version'])){
						$plugins[$_pluginInfo['type']][$plugin] = $_pluginInfo;
					}
					$this->writePlConf($plugin,$_pluginInfo['type']);
				}
			}
		}

		if(isset($plugins)&&is_array($plugins)&&count($plugins)>0){
			$temp_plugins = array();
			foreach ($plugins as $type => $tplugins){
				$temp_plugins = array_merge($temp_plugins,$tplugins);
			}
			$this->availablePlugins = $temp_plugins;
			return $plugins;
		}else{
			return false;
		}
	}

	public function getPluginByDomain($user){
		$user_domain = explode('@',$user);
		if(isset($user_domain[1])){
			$user_domain = $user_domain[1];
			if(isset($this->availablePlugins)&&is_array($this->availablePlugins)){
				foreach ($this->availablePlugins as $plugin => $details){
					$patterns = array();
					if(isset($details['allowed_domains'])){
						$patterns = $details['allowed_domains'];
					}else{
						if(isset($details['detected_domains'])){
							$patterns = $details['detected_domains'];
						}
					}
					foreach($patterns as $domain_pattern){
						if(preg_match($domain_pattern,$user_domain)){
							return $plugin;
						}
					}
					return false;
				}
			}else{
				return false;
			}
		}else{
			return false;
		}
	}

	public function writePlConf($file = null, $type = null){
		if(isset($file)&&isset($type)&&(strtolower($type)=='email'||strtolower($type)=='social')){
			if(!file_exists($this->confpath)){
				mkdir($this->confpath,0755,true);
			}
			if(file_exists($this->confpath)){
				switch(strtolower($type)){
					case 'social':
						$written = file_put_contents($this->confpath.DS.$file.'.conf','<?php $enable=true;$autoUpdate=true;$messageDelay=1;$maxMessages=10;?>');
					break;
					case 'email':
						$written = file_put_contents($this->confpath.DS.$file.'.conf','<?php $enable=true;$autoUpdate=true; ?>');
					break;
					case 'hosted':
						$written = file_put_contents($this->confpath.DS.$file.'.conf','<?php $enable=false;$autoUpdate=true; ?>');
					break;
					default:
						return false;
				}
				if(isset($written)){
					if($written===false){
						return false;
					}else{
						return true;
					}
				}else{
					return false;
				}
			}else{
				return false;
			}
		}else{
			return false;
		}
	}

	/**
	* Check version requirements
	* 
	* checks if the current version of OpenInviter is greater than the plugin's required version
	* 
	* @param string $required_version The OpenInviter version that the plugin requires.
	* @return bool TRUE if the version if equal or greater, FALSE otherwise.
	*/
	public function checkVersion($version){
		if(version_compare($version,$this->version,'<=')){
			return true;
		}else{
			return false;
		}
	}

	/**
	* start internal plugin
	* 
	* starts the internal plugin and
	* transfers the settings to it.
	* 
	* @param string $plugin The name of the plugin being started
	*/
	public function startPlugin($plugin,$getPlugins = false){
		if($getPlugins){
			$this->internalError = "Hosted solution disabled";
			return false;
		}else{
			if($this->statsCheck()){
				if(file_exists($this->pluginspath.DS.$plugin.'.plg.php')){
					if(!class_exists($plugin)){
						vendor($this->pluginpath.DS.$plugin.'.plg');
					}

					if(class_exists($plugin)){

						$this->plugin = new $plugin();

						if(isset($this->plugin)&&is_object($this->plugin)){

							if(isset($this->availablePlugins[$plugin])){

								$this->currentPlugin = $this->availablePlugins[$plugin];

								$this->plugin->base_path = $this->oipath;
								$this->plugin->base_version = $this->version;
								$this->plugin->settings = array('transport' => $this->transport,'cookiespath' => $this->cookiespath);

								if(file_exists($this->confpath.DS.$plugin.'.conf')){ 
									include($this->confpath.DS.$plugin.'.conf');
									if(isset($enable)){
										$this->plugin->maxMessages = ((isset($maxMessages)&&is_numeric($maxMessages)) ? $maxMessages : 10);
										$this->plugin->messageDelay = ((isset($messageDelay)&&is_numeric($messageDelay)) ? $messageDelay : 1);
									}else{
										$this->internalError = "Invalid service configuration file for [".$plugin."]";
										return false;
									}
								}
								return true;
							}else{
								$this->internalError = "Unable to find plugin in found plugins list [".$plugin."]";
								return false;
							}
						}else{
							$this->internalError = "Unable to create new plugin object for [".$plugin."]";
							return false;
						}
					}else{
						$this->internalError = "Unable to loag plugin class for [".$plugin."]";
						return false;
					}
				}else{
					$this->internalError = "Invalid service provider [".$plugin."]";
					return false;
				}

			}else{
				return false;
			}
		}

	}

	/**
	* stop the internal plugin
	* 
	* acts as a wrapper function for the stopPlugin
	* function in the _base class
	*/
	public function stopPlugin($graceful = false){
		if(isset($this->plugin)&&is_object($this->plugin)&&method_exists($this->plugin,'stopPlugin')){
			$this->plugin->stopPlugin($graceful);
		}
	}

	/**
	* Login function
	* 
	* acts as a wrapper function for the plugin's login function.
	* 
	* @param string $user The username being logged in
	* @param string $pass The password for the username being logged in
	* @return mixed FALSE if the login credentials don't match the plugin's requirements or the result of the plugin's login function.
	*/
	public function login($user,$pass){
		if(isset($this->plugin)&&is_object($this->plugin)&&method_exists($this->plugin,'login')&&$this->checkLoginCredentials($user)){
			return $this->plugin->login($user,$pass);
		}else{
			return false;
		}
	}

	/**
	* check the provided login credentials
	* 
	* checks whether the provided login credentials match the plugin's required structure and (if required) if the provided domain name is allowed for the current plugin.
	* 
	* @param string $user The provided user name.
	* @return bool TRUE if the login credentials match the required structure, FALSE otherwise. 
	*/
	private function checkLoginCredentials($user){
		if(isset($this->plugin)&&is_object($this->plugin)&&method_exists($this->plugin,'isEmail')){

			$is_email = $this->plugin->isEmail($user);

			//requirements : email or user only
			if(isset($this->currentPlugin['requirement'])){
				//full email only
				if($this->currentPlugin['requirement']=='email'&&$is_email==false){
					$this->internalError = 'Please enter the full email, not just the username';
					return false;
				}else{
					//username only
					if($this->currentPlugin['requirement']=='user'&&$is_email!=false){
						$this->internalError = 'Please enter just the username, not the full email';
						return false;
					}
				}
			}

			//allowed user domain name
			if(isset($this->currentPlugin['allowed_domains'])&&is_array($this->currentPlugin['allowed_domains'])&&$is_email!=false){
				$user_domain = explode('@',$user);
				//checking user domain name
				if(isset($user_domain[1])&&is_string($user_domain[1])){
					$user_domain = $user_domain[1];
					foreach($this->currentPlugin['allowed_domains'] as $domain){
						if(preg_match($domain,$user_domain)){
							$allowed = true;
							break;
						}
					}
					if(isset($allowed)&&$allowed){
						return true;
					}else{
						if(isset($user_domain)&&is_string($user_domain)){
							$this->internalError = $user_domain.' is not a valid domain for this provider';
							return false;
						}else{
							$this->internalError = 'invalid domain for this provider';
							return false;
						}
					}
				}else{
					$this->internalError = 'invalid domain for this provider';
					return false;
				}
			}else{
				//valid user
				return true;
			}
		}else{
			return false;
		}
	}

	/**
	* end the current user's session
	* 
	* acts as a wrapper function for the plugin's logout function
	* 
	* @return bool The result of the plugin's logout function.
	*/
	public function logout(){
		if(isset($this->plugin)&&is_object($this->plugin)&&method_exists($this->plugin,'logout')){
			return $this->plugin->logout();
		}else{
			return false;
		}
	}

	/**
	* get the current user's contacts
	* 
	* acts as a wrapper function for the plugin's getMyContacts function.
	* 
	* @return mixed The result of the plugin's getMyContacts function.
	*/
	public function getMyContacts(){
		if(isset($this->plugin)&&is_object($this->plugin)&&method_exists($this->plugin,'getMyContacts')){
			$contacts = $this->plugin->getMyContacts();
			if($contacts!==false){
				$this->statsRecordImport(count($contacts));
			}
			return $contacts;
		}else{
			return false;
		}
	}

	/**
	* find out if the contacts should be displayed
	* 
	* tells whether the current plugin will display a list of contacts or not
	* 
	* @return bool TRUE if the plugin displays the list of contacts, FALSE otherwise.
	*/
	public function showContacts(){
		if(isset($this->plugin)&&is_object($this->plugin)&&isset($this->plugin->showContacts)){
			return $this->plugin->showContacts;
		}else{
			return false;
		}
	}

	/**
	* send a message
	* 
	* acts as a wrapper for the plugin's sendMessage function.
	* 
	* @param string $session_id The OpenInviter user's session ID
	* @param string $message The message being sent to the users
	* @param array $contacts An array of contacts that are going to receive the message
	* @return mixed -1 if the plugin doesn't have an internal sendMessage function or the result of the plugin's sendMessage function
	*/
	public function sendMessage($session_id = null,$message = null,$contacts = null){
		if(isset($this->plugin)&&is_object($this->plugin)){
			if(method_exists($this->plugin,'init')){

				$this->plugin->init($session_id);
				$internal = $this->getInternalError();

				if($internal){
					return false;
				}else{
					if(method_exists($this->plugin,'sendMessage')){
						$sent = $this->plugin->sendMessage($session_id,$message,$contacts);
						if($sent!==false){
							if(isset($contacts)&&is_array($contacts)){
								$this->statsRecordMessages('I',count($contacts));
							}else{
								$this->statsRecordMessages('I',0);
							}
						}
						return $sent;
					}else{
						if(isset($contacts)&&is_array($contacts)){
							$this->statsRecordMessages('E',count($contacts));
						}else{
							$this->statsRecordMessages('E',0);
						}
						return -1;
					}
				}
			}else{
				return false;
			}
		}else{
			return false;
		}
	}

	/**
	* gets the OpenInviter's internal error
	* 
	* gets the OpenInviter's base class or the plugin's internal error message
	* 
	* @return mixed the error message or FALSE if there is no error
	*/
	public function getInternalError(){
		if(isset($this->internalError)){
			return $this->internalError;
		}else{
			if(isset($this->plugin)&&is_object($this->plugin)&&isset($this->plugin->internalError)){
				if(isset($this->plugin->internalError)){
					return $this->plugin->internalError;
				}else{
					return false;
				}
			}else{
				return false;
			}
		}
	}

	/**
	* get the current OpenInviter session ID
	* 
	* acts as a wrapper function for the plugin's getSessionID function.
	* 
	* @return mixed The result of the plugin's getSessionID function.
	*/
	public function getSessionID(){
		if(isset($this->plugin)&&is_object($this->plugin)&&method_exists($this->plugin,'getSessionID')){
			return $this->plugin->getSessionID();
		}else{
			return false;
		}
	}

	private function arrayToText($array){
		$i = 0;
		$text = '';
		$flag = false;

		foreach ($array as $key=>$val){
			if($flag){
				$text.=",\n";
			}

			$flag = true;
			$text .= "'{$key}'=>";

			if(is_array($val)){
				$text.='array('.$this->arrayToText($val).')';
			}elseif(is_bool($val)){
				$text.=($val?'true':'false');
			}else{
				$text.="\"{$val}\"";
			}
		}

		return($text);
	}
}
?>