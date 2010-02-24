<?php
/**
 * The core of the OpenInviter system
 * 
 * Contains methods and properties used by all the OpenInviter plugins
 * 
 * @author Sami Radi
 * @version 1.7.6b
 */
abstract class openinviter_base{

	protected $session_id;

	public $service;
	public $service_user;
	public $service_password;

	public $settings;

	private $curl;
	private $maxMessages;
	private $messageDelay;
	private $errors = false;
	private $debug = array();

	abstract function logout();
	abstract function getMyContacts();
	abstract function login($user,$pass);

	/********desactivated debug functions********/

	private function remoteDebug(){return true;}

	private function buildDebugXML(){return null;}

	protected function logAction($message,$type='error'){return null;}

	/********desactivated debug functions********/

	/**
	 * Update the internal debug buffer
	 * 
	 * Updates the internal debug buffer with information
	 * about the request just performed and it's state
	 * 
	 * @param string $step The name of the step being debugged
	 * @param string $url The URL that was being requested
	 * @param string $method The method used to request the URL (GET/POST)
	 * @param bool $response The state of the request
	 * @param mixed $elements An array of elements being sent in the request or FALSE if no elements are sent.
	 */
	protected function updateDebugBuffer($step, $url, $method, $response = true,$elements = false){
		$this->debug[$step] = array(
			'url'=> $url,
			'method'=> $method
		);

		if($elements){
			foreach ($elements as $name => $value){
				$this->debug[$step]['elements'][$name] = $value;
			}
		}else{
			$this->debug[$step]['elements'] = false;
		}

		if($response){
			$this->debug[$step]['response'] = 'OK';
		}else{
			$this->errors = true;
			$this->debug[$step]['response'] = 'FAILED';
		}
	}

	/**
	 * Execute the debugger
	 * 
	 * @return bool FALSE if the debugged session contained any errors, TRUE otherwise.
	 */
	protected function debugRequest(){
		if(isset($this->errors)&&$this->errors){
			$this->localDebug();
			return false;
		}else{
			return true;
		}
	}

	/**
	 * build debug information
	 */
	protected function localDebug(){
		$xml = "OpenInviter Debug";
		$xml .= "\n----------DETAILS START----------\n";
		$xml .= $this->buildDebugHuman();
		$xml .= "\n----------DETAILS END----------\n";
	}

	/**
	 * Transform the debug buffer in a human readable form
	 * 
	 * Parses the debug buffer and renders it in a human readable form
	 * 
	 * @return string The debug buffer in a human readable form
	 */
	private function buildDebugHuman(){

		$debug = "\nTRANSPORT: {$this->settings['transport']}\n";
		$debug.= "STEPS: \n";

		foreach ($this->debug as $step => $details){

			$debug.= "\t{$step} :\n";
			$debug.= "\t\tURL: {$details['url']}\n";
			$debug.= "\t\tMETHOD: {$details['method']}\n";

			if(strtoupper($details['method'])=='POST'){
				$debug.= "\t\tELEMENTS: ";
				if($details['elements']){
					$debug.= "\n";
					foreach($details['elements'] as $name => $value){
						$debug.= "\t\t\t{$name}={$value}\n";
					}
				}else{
					$debug.= "(no elements sent in this request)\n";
				}
			}
			$debug.= "\t\tRESPONSE: {$details['response']}\n";
		}

		(isset($this->internalError)) ? $this->internalError .= $debug : $this->internalError = $debug;
	}

	/**
	 * Reset the debugger
	 * 
	 * Empties the debug buffer and resets the errors trigger
	 */
	protected function resetDebugger(){
		$this->errors = false;
		$this->debug = array();
	}

	/**
	* Stops the internal plugin
	* 
	* Stops the internal plugin deleting the cookie
	* file or keeping it is the stop is being graceful
	* 
	* @param bool $graceful
	*/
	public function stopPlugin($graceful = false){
		if(isset($this->settings['transport'])&&$this->settings['transport']=='curl'){
			curl_close($this->curl);
		}
		if($graceful===false){
			$this->endSession();
		}
	}

	/**
	 * Intialize transport
	 * 
	 * Intializes the transport being used for request
	 * taking into consideration the settings and creating
	 * the file being used for storing cookie.
	 * 
	 * @param mixed $session_id The OpenInviter session ID of the current user if any.
	 */
	public function init($session_id = false){

		$session_start = $this->startSession($session_id);

		if($session_start===false){
			return false;
		}else{

			$file = $this->getCookiePath();
			$this->proxy = $this->getProxy();

			if($session_id===false){
				$fop = fopen($file,"wb");
				fclose($fop);
			}

			if($this->settings['transport']=='curl'){
				$this->curl = curl_init();
				curl_setopt($this->curl, CURLOPT_USERAGENT,((isset($this->userAgent)&&!empty($this->userAgent)) ? $this->userAgent : "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.1) Gecko/2008070208 Firefox/3.0.1"));
				curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, false);
				curl_setopt($this->curl, CURLOPT_COOKIEFILE, $file);
				curl_setopt($this->curl, CURLOPT_HEADER, false);
				curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($this->curl, CURLOPT_SSL_VERIFYHOST, false);
				curl_setopt($this->curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
				curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($this->curl, CURLOPT_COOKIEJAR, $file);
				if(strtoupper(substr(PHP_OS, 0,3))=='WIN'){
					curl_setopt($this->curl, CURLOPT_CONNECTTIMEOUT, ((isset($this->timeout)&&is_numeric($this->timeout)) ? $this->timeout : 5)/2);
				}else{
					curl_setopt($this->curl, CURLOPT_TIMEOUT, ((isset($this->timeout)&&is_numeric($this->timeout)) ? $this->timeout : 5));
				}
				curl_setopt($this->curl, CURLOPT_AUTOREFERER, TRUE);
				if(isset($this->proxy)&&is_array($this->proxy)&&isset($this->proxy['host'])&&isset($this->proxy['port'])&&count($this->proxy)>1){
					curl_setopt($this->curl, CURLOPT_PROXY, $this->proxy['host']);
					curl_setopt($this->curl, CURLOPT_PROXYPORT, $this->proxy['port']);
					if(isset($this->proxy['user'])&&isset($this->proxy['password'])){
						curl_setopt($this->curl, CURLOPT_PROXYUSERPWD, $this->proxy['user'].':'.$this->proxy['password']); 
					}
				}
			}
			return true;
		}
	}

	/**
	* Execute a GET request
	* 
	* Executes a GET request to the provided URL
	* taking into consideration the settings and
	* request options.
	* 
	* @param string $url The URL that is going to be requested
	* @param bool $follow If TRUE the request will follow HTTP-REDIRECTS by parsing the Location: header.
	* @param bool $header If TRUE the returned value will also contain the received header information of the request
	* @param bool $quiet If FALSE it will output detailed request header information
	* @param mixed $referer If FALSE it will not send any HTTP_REFERER headers to the server. Otherwise the value of this variable is the HTTP_REFERER sent.
	* @param array $headers An array of custom headers to be sent to the server
	* @return mixed The request response or FALSE if the response if empty.
	*/
	protected function get($url,$follow = false,$header = false,$quiet = true,$referer = false,$headers = array()){
		if($this->settings['transport']=='curl'){

			curl_setopt($this->curl, CURLOPT_URL, $url);
			curl_setopt($this->curl, CURLOPT_POST, false);
			curl_setopt($this->curl, CURLOPT_HTTPGET , true);

			if(isset($headers)&&is_array($headers)&&count($headers)>0){
				$curl_headers = array();
				foreach($headers as $header_name => $value){
					$curl_headers[]="{$header_name}: {$value}";
				}
				curl_setopt($this->curl,CURLOPT_HTTPHEADER,$curl_headers);
			}

			if($header||$follow){
				curl_setopt($this->curl, CURLOPT_HEADER, true);
			}else{
				curl_setopt($this->curl, CURLOPT_HEADER, false);
			}

			if($referer){
				curl_setopt($this->curl, CURLOPT_REFERER, $referer);
			}else{
				curl_setopt($this->curl, CURLOPT_REFERER, '');
			}

			$result = curl_exec($this->curl);

			if($follow){
				$new_url=$this->followLocation($result,$url);
				if(!empty($new_url)){
					$result = $this->get($new_url,$follow,$header,$quiet,$url,$headers);
				}
			}
			return $result;
		}else{
			if($this->settings['transport']=='wget'){

				$string_wget="--user-agent=\"Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.1) Gecko/2008070208 Firefox/3.0.1\"";
				$string_wget.=" --timeout=".(isset($this->timeout)?$this->timeout:5);
				$string_wget.=" --no-check-certificate";
				$string_wget.=" --load-cookies ".$this->getCookiePath();

				if(isset($headers)&&is_array($headers)&&count($headers)>0){
					foreach ($headers as $header_name => $value){
						$string_wget.=" --header=\"".escapeshellcmd($header_name).": ".escapeshellcmd($value)."\"";
					}
				}

				if($header){
					$string_wget.=" --save-headers";
				}

				if($referer){
					$string_wget.=" --referer={$referer}";
				}

				$string_wget.=" --save-cookies ".$this->getCookiePath();
				$string_wget.=" --keep-session-cookies";
				$string_wget.=" --output-document=-";

				$url = escapeshellcmd($url);
				if($quiet){
					$string_wget.=" --quiet";
				}else{
					$log_file=$this->getCookiePath().'_log';
					$string_wget.=" --output-file=\"{$log_file}\"";
				}

				$command = "wget {$string_wget} {$url}";

				if(isset($this->proxy)&&is_array($this->proxy)&&isset($this->proxy['host'])&&isset($this->proxy['port'])&&count($this->proxy)>1){
					$proxy_url = 'http://'.((isset($this->proxy['user'])&&!empty($this->proxy['user'])) ? $this->proxy['user'].':'.$this->proxy['password'].'@' : '').$this->proxy['host'].':'.$this->proxy['port'];
					$command = "export http_proxy={$proxy_url} && ".$command;
				}

				ob_start();
				passthru($command,$return_var);
				$buffer = ob_get_contents();
				ob_end_clean();

				if(!$quiet){
					$buffer = file_get_contents($log_file).$buffer;
					unlink($log_file);
				}

				if((strlen($buffer)==0)||($return_var!=0)){
					return false;
				}else{
					return $buffer;
				}
			}else{
				return false;
			}
		}
	}

	/**
	 * Execute a POST request
	 * 
	 * Executes a POST request to the provided URL
	 * taking into consideration the settings and
	 * request options.
	 * 
	 * @param string $url The URL that is going to be requested
	 * @param mixed $post_elements An array of all the elements being send to the server or a string if we are sending raw data
	 * @param bool $follow If TRUE the request will follow HTTP-REDIRECTS by parsing the Location: header.
	 * @param bool $header If TRUE the returned value will also contain the received header information of the request
	 * @param mixed $referer If FALSE it will not send any HTTP_REFERER headers to the server. Otherwise the value of this variable is the HTTP_REFERER sent.
	 * @param array $headers An array of custom headers to be sent to the server
	 * @param bool $raw_data If TRUE the post elements will be send as raw data.
	 * @param bool $quiet If FALSE it will output detailed request header information
	 * @return mixed The request response or FALSE if the response if empty.
	 */
	protected function post($url, $post_elements, $follow = false, $header = false,$referer = false,$headers = array(),$raw_data = false,$quiet = true){
		$flag = false;

		if ($raw_data){
			$elements=$post_elements;
		}else{
			$elements='';
			foreach ($post_elements as $name=>$value){
				if($flag){
					$elements.='&';
				}
				$elements.="{$name}=".urlencode($value);
				$flag = true;
			}
		}

		if($this->settings['transport']=='curl'){

			curl_setopt($this->curl, CURLOPT_URL, $url);
			curl_setopt($this->curl, CURLOPT_POST,true);

			if(isset($headers)&&is_array($headers)&&count($headers)>0){
				$curl_headers=array();
				foreach ($headers as $header_name=>$value){
					$curl_headers[]="{$header_name}: {$value}";
				}
				curl_setopt($this->curl,CURLOPT_HTTPHEADER,$curl_headers);
			}

			if ($referer){
				curl_setopt($this->curl, CURLOPT_REFERER, $referer);
			}else{
				curl_setopt($this->curl, CURLOPT_REFERER, '');
			}

			if($header||$follow){
				curl_setopt($this->curl, CURLOPT_HEADER, true);
			}else{
				curl_setopt($this->curl, CURLOPT_HEADER, false);
			}

			curl_setopt($this->curl, CURLOPT_POSTFIELDS, $elements);
			$result = curl_exec($this->curl);

			if($follow){
				$new_url = $this->followLocation($result,$url);
				if($new_url){
					$result = $this->get($new_url,$post_elements,$follow,$header,$url,$headers,$raw_data);
				}
			}

			return $result;

		}else{
			if($this->settings['transport']=='wget'){

				$string_wget="--user-agent=\"Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.1) Gecko/2008070208 Firefox/3.0.1\"";
				$string_wget.=" --timeout=".(isset($this->timeout)?$this->timeout:5);
				$string_wget.=" --no-check-certificate";
				$string_wget.=" --load-cookies ".$this->getCookiePath();

				if(isset($headers)&&is_array($headers)&&count($headers)>0){
					foreach ($headers as $header_name => $value){
						$string_wget.=" --header=\"".escapeshellcmd($header_name).": ".escapeshellcmd($value)."\"";
					}
				}

				if($header){
					$string_wget.=" --save-headers";
				}

				if($referer){
					$string_wget.=" --referer=\"{$referer}\"";
				}

				$string_wget.=" --save-cookies ".$this->getCookiePath();
				$string_wget.=" --keep-session-cookies";
				$url = escapeshellcmd($url);
				$string_wget.=" --post-data=\"{$elements}\"";
				$string_wget.=" --output-document=-";

				if($quiet){
					$string_wget.=" --quiet";
				}else{
					$log_file=$this->getCookiePath().'_log';
					$string_wget.=" --output-file=\"{$log_file}\"";
				}

				$command="wget {$string_wget} {$url}";
				ob_start();
				passthru($command,$return_var);
				$buffer = ob_get_contents();
				ob_end_clean();

				if(!$quiet){
					$buffer = file_get_contents($log_file).$buffer;
					unlink($log_file);
				}

				if((strlen($buffer)==0)||($return_var!=0)){
					return false;
				}else{
					return $buffer;
				}
			}else{
				return false;
			}
		}
	}

	protected function startSession($session_id = false){
		if($session_id===false){
			$this->session_id = $this->getSessionID();
			return true;
		}else{

			$path = $this->getCookiePath($session_id);

			if(file_exists($path)){
				$this->session_id = $session_id;
				return true;
			}else{
				$this->internalError = "Invalid session ID : [".$session_id."]";
				return false;
			}
		}
	}

	protected function endSession(){
		if($this->checkSession()){

			$path = $this->getCookiePath($this->session_id);

			if(file_exists($path)){
				unlink($path);
			}

			$path = $this->getLogoutPath($this->session_id);

			if(file_exists($path)){
				unlink($path);
			}

			unset($this->session_id);
		}
	}

	/**
	* Check for an active session
	* 
	* Checks if there is any active session
	* 
	* @return bool TRUE if there is an active session, FALSE otherwise.
	*/
	protected function checkSession(){
		if(empty($this->session_id)){
			return false;
		}else{
			return true;
		}
	}

	/**
	* Get the OpenInviter session ID
	* 
	* Gets the current OpenInviter session ID or
	* creates one if there is no active session.
	* 
	* @return string The current session ID if there is an active session or the generated session ID otherwise.
	*/
	public function getSessionID(){
		if(empty($this->session_id)){
			return time().'.'.rand(1,10000);
		}else{
			return $this->session_id;
		}
	}

	/**
	* Get the cookies file path
	* 
	* Gets the path to the file storing all
	* the cookie for the current session
	* 
	* @return string The path to the cookies file.
	*/
	protected function getCookiePath($session_id = false){
		if($session_id===false){
			$path = $this->settings['cookiespath'].DIRECTORY_SEPARATOR.'oi.'.$this->getSessionID().'.cookie';
		}else{
			$path = $this->settings['cookiespath'].DIRECTORY_SEPARATOR.'oi.'.$session_id.'.cookie';
		}
		return $path;
	}

	/**
	* Get the logout file path
	* 
	* Gets the path to the file storing the
	* logout link.
	* 
	* @return string The path to the file storing the logout link.
	*/
	protected function getLogoutPath($session_id = false){
		if($session_id===false){
			$path = $this->settings['cookiespath'].DIRECTORY_SEPARATOR.'oi.'.$this->getSessionID().'.logout';
		}else{
			$path = $this->settings['cookiespath'].DIRECTORY_SEPARATOR.'oi.'.$session_id.'.logout';
		}
		return $path;
	}

	/**
	* Get a random proxy
	* 
	* @return string proxy ip.
	*/
	protected function getProxy(){
		if(isset($this->settings['proxies'])&&is_array($this->settings['proxies'])){
			if(count($this->settings['proxies'])==1){
				reset($this->settings['proxies']);
				return current($this->settings['proxies']);
			}else{
				$this->settings['proxies'][array_rand($this->settings['proxies'])];
			}
		}else{
			return false;
		}
	}

	/**
	 * Extract Location: header
	 * 
	 * Extracts Location: header from a POST or GET
	 * request that includes the header information
	 * 
	 * @param string $result The request result including header information
	 * @param string $old_url The url in which the request was initially made
	 * @return string The URL that it is being redirected to
	 */
	protected function followLocation($result = null,$old_url = null){
		if(isset($result)&&isset($old_url)){
			if((strpos($result,"HTTP/1.1 3")===false)&&(strpos($result,"HTTP/1.0 3")===false)){
				return false;
			}else{
				$new_url = trim($this->getElementString($result,"Location: ",PHP_EOL));
				if(empty($new_url)){
					$new_url = trim($this->getElementString($result,"location: ",PHP_EOL));
				}
				if(empty($new_url)){
					return false;
				}else{
					if(strpos($new_url,'http')===false){
						$url = parse_url($old_url);
						$new_url = $url['scheme'].'://'.$url['host'].($new_url[0]=='/'?'':'/').$new_url;
					}
					return $new_url;
				}
			}
		}else{
			return false;
		}
	}

	/**
	 * Check a request's response
	 * 
	 * Checks if a request was successful by
	 * searching for a token inside it
	 * 
	 * @param string $step The name of the step being checked
	 * @param string $server_response The bulk request response
	 * @return bool TRUE if successful, FALSE otherwise.
	 */
	protected function checkResponse($step,$server_response){
		if(empty($server_response)){
			return false;
		}else{
			if(strpos($server_response,$this->debug_array[$step])===false){
				return false;
			}else{
				return true;
			}
		}
	}

	protected function returnContacts($contacts = array()){
		if(isset($contacts)&&is_array($contacts)){
			$returnedContacts = array();
			$fullImport = array('first_name','middle_name','last_name','nickname','email_1','email_2','email_3','organization','phone_mobile','phone_home','phone_work','fax','pager','address_home','address_city','address_state','address_country','postcode_home','company_work','address_work','address_work_city','address_work_country','address_work_state','address_work_postcode','fax_work','phone_work','website','isq_messenger','skype_messenger','skype_messenger','msn_messenger','yahoo_messenger','aol_messenger','other_messenger');
			if((!isset($this->settings['fImport']))||empty($this->settings['fImport'])){
				foreach($contacts as $keyImport => $arrayImport){
					$name = trim((!empty($arrayImport['first_name']) ? $arrayImport['first_name'] : false).' '.(!empty($arrayImport['middle_name']) ? $arrayImport['middle_name'] : false ).' '.(!empty($arrayImport['last_name']) ? $arrayImport['last_name'] : false).' '.(!empty($arrayImport['nickname']) ? $arrayImport['nickname'] : false));
					$returnedContacts[$keyImport]= (!empty($name) ? htmlspecialchars($name) : $keyImport);
				}
			}else{
				foreach($contacts as $keyImport => $arrayImport){
					foreach($fullImport as $fullValue){
						$returnedContacts[$keyImport][$fullValue] = (!empty($arrayImport[$fullValue]) ? $arrayImport[$fullValue] : false);
					}
				}
			}
			return $returnedContacts;
		}else{
			return array();
		}
	}

	public function isEmail($email){
		return preg_match("/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/i", $email);
	}

	/**
	* Parse a CSV string into an array
	* 
	* Parses the CSV data from a string into an array,
	* reading the first line of the bulk as the CSV header
	* 
	* @param string $file The CSV bulk
	* @param string $delimiter The character that separates the values of two fields
	* @return mixed The array of CSV entries or FALSE if the CSV has no entries
	*/
	protected function parseCSV($file = null, $delimiter=','){
		if(isset($file)&&is_string($file)){
			$count = 0;
			$res = array();
			$lines = explode("\n", $file);
			$fields = explode($delimiter, array_shift($lines));
			$pattern = "/,(?=(?:[^\"]*\"[^\"]*\")*(?![^\"]*\"))/";

			foreach($fields as $key => $field){
				$fields[$key] = $count;
				$count++;
			}

			foreach($lines as $line){
				if(empty($line)){
					continue;
				}else{
					$_res = array();
					$columns = preg_replace("/^\"(.*)\"$/","$1",preg_split($pattern,trim($line)));
					foreach ($fields as $key => $value){
						$_res[$value] = (isset($columns[$key])? $columns[$key] : false);
					}
					$res[] = $_res;
				}
			}

			if(empty($res)){
				return false;
			}else{
				return $res;
			}
		}else{
			return false;
		}
	}

	/**
	* Execute an  XPath query
	* 
	* Executes an XPath query on a HTML bulk,
	* extracting either an attribute or the node value
	* 
	* @param string $htmlstring The HTML string the XPath is executed onto
	* @param string $query The XPath query that is being evaluated
	* @param string $type The target of the query (an attribute or the node value)
	* @param string $attribute The attribute's value to be extracted.
	* @return mixed Returns the result array of the XPath or FALSE if no values were found
	*/
	protected function getElementDOM($htmlstring = null,$query = null,$attribute = false){

		$values = array();

		$document = new DOMDocument();
		libxml_use_internal_errors(true);

		if(empty($htmlstring)){
			return false;
		}else{
			if(isset($document)&&is_object($document)){
				$document->loadHTML($htmlstring);
				libxml_use_internal_errors(false);

				$xpath = new DOMXPath($document);

				if(isset($query)&&isset($xpath)&&is_object($xpath)){

					$nodes = $xpath->query($query);

					if(isset($attribute)&&$attribute!==false){
						foreach($nodes as $node){
							$values[]= $node->getAttribute($attribute);
						}
					}else{
						foreach($nodes as $node){
							$values[]= $node->nodeValue;
						}
					}

					if(empty($values)){
						return false;  
					}else{
						return $values;
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
	* Extracts hidden elements from a HTML bulk
	* 
	* Extracts all the <input type='hidden'> elements
	* from a HTML bulk
	* 
	* @param string $htmlstring The HTML bulk from which the fields are extracted
	* @return array An array shaped as name=>value of all the <input type='hidden'> fields  
	*/
	protected function getHiddenElements($htmlstring = null){

		$document = new DOMDocument();
		libxml_use_internal_errors(true);

		if(empty($htmlstring)){
			return null;
		}else{
			if(isset($document)&&is_object($document)){

				$document->loadHTML($htmlstring);
				libxml_use_internal_errors(false);

				$xpath = new DOMXPath($document);
				$query = "//input[@type='hidden']";


				if(isset($query)&&isset($xpath)&&is_object($xpath)){

					$elements = array();
					$nodes = $xpath->query($query);

					foreach($nodes as $node){
						$name = $node->getAttribute('name');
						$value = $node->getAttribute('value');
						$elements[(string)$name] = (string)$value;
					}

					return $elements;
				}else{
					return false;
				}
			}else{
				return null;
			}
		}
	}

	/**
	* Extract a substring from a string
	* 
	* Extracts a substring that is found between two
	* tokens from a string
	* 
	* @param string $string_to_search The main string that is being processed
	* @param  string $string_start The start token from which the substring extraction begins
	* @param string $string_end The end token where which marks the substring's end
	* @return string The substring that is between the start and end tokens
	*/
	protected function getElementString($string_to_search = null,$string_start = null,$string_end = null){
		if(strpos($string_to_search,$string_start)===false){
			return false;
		}else{
			if(strpos($string_to_search,$string_end)===false){
				return false;
			}else{
				$start = strpos($string_to_search,$string_start)+strlen($string_start);
				$end = strpos($string_to_search,$string_end,$start);
				$return = substr($string_to_search,$start,$end-$start);
				return $return;
			}
		}
	}
}
?>
