<?php

	/*

		Sheldon Lendrum
		FileMaker-Data-API-PHP-Wrapper

	*/


	class Fmapi {

		public $host = '';
		public $db = '';
		public $user = '';
		public $pass = '';

		public $version = 'vLatest';
		public $fmversion = 19;
		public $layout = '';

		public $cache_hours = 0;
		public $cache_name = FALSE;
		public $cache_path = FALSE;
		public $cache_bust = FALSE;

		public $secure = FALSE;
		public $token_name = 'fmtoken';
		public $show_debug = FALSE; //  'HTML';

		public $queries = [];
		public $errors = [];
		public $debug_array = [];






		public function __construct()
		{
			$this->_reset();

		}

		public function host($host = NULL)
		{
			$this->host = $host;
			return $this;
		}

		public function db($db = NULL)
		{
			$this->db = $db;
			return $this;
		}

		public function user($user = NULL)
		{
			$this->user = $user;
			return $this;
		}

		public function pass($pass = NULL)
		{
			$this->pass = $pass;
			return $this;
		}

		public function cache_path($cache_path = NULL)
		{
			$this->cache_path = $cache_path;
			return $this;
		}

		public function last_error()
		{
			return end($this->errors);
		}

		public function errors()
		{
			return $this->errors;
		}

		public function cache($hours = NULL)
		{
			$this->cache_hours = (int)$hours;
			return $this;
		}


		public function bust($bust = FALSE)
		{
			$this->cache_bust = $bust;
			$this->cache_hours = 0;
			return $this;
		}



		public function order_by($field, $direction)
		{

			$this->order_by[$field] = $direction;
			return $this;
		}

		public function get($layout)
		{
			$this->result = FALSE;
			$this->layout = $layout;
			$this->cache_name = "SELECT FROM {$layout}";
			$this->_query();
			return $this;
		}

		public function update($layout, $update_data, $fm_id)
		{
			return $this->editRecord($fm_id, ['fieldData' => $update_data], $layout);
		}

		public function script($layout, $script_name, $script_params = NULL)
		{
			return $this->executeScript($script_name, json_encode($script_params), $layout);
		}

		public function insert($layout, $create_data)
		{
			return $this->createRecord(['fieldData' => $create_data], $layout);
		}

		public function delete($layout, $fm_id)
		{
			return $this->deleteRecord($fm_id, ['fieldData' => NULL], $layout);
		}

		public function where($key, $value = NULL)
		{

			$this->where[$key] = $value;
			return $this;

		}

		public function limit($limit = 1)
		{
			$this->limit = $limit;
			return $this;

		}

		public function row()
		{

			if(empty($this->result)) return FALSE;
			return reset($this->result);

		}

		public function result()
		{
			if(empty($this->result)) return FALSE;
			return $this->result;

		}


		public function container($url = NULL)
		{

			// To-do

		}

		private function _reset()
		{
			$this->where = [];
			$this->limit = 99999;
			$this->cache_hours = 0;
			$this->cache_name = FALSE;
			$this->cache_bust = FALSE;
		}

		private function _query()
		{


			$login = $this->login();
			if(!$this->checkValidLogin($login)) return $login;

			$data = [];

			if(empty($this->where)) {

				$this->cache_name .= " GET ALL ";

				$this->queries[] = $this->cache_name;

				if($this->is_cached()) {
					return $this;
				}

				$result = $this->getRecords($data, $this->layout);
			} else {

				$this->cache_name .= " WHERE ";
				foreach($this->where as $f => $v) {
					$this->cache_name .= " `{$f}` = '{$v}' AND ";
				}
				$query = [$this->where];
				$this->cache_name .= ' LIMIT = '. $this->limit;

				$this->queries[] = $this->cache_name;

				if($this->is_cached()) {
					return $this;
				}

				$data['limit'] = (int)$this->limit;
				$data['query'] = $query;
				$result = $this->findRecords($data, $this->layout);
			}

			$cache_hours = $this->cache_hours;
			$cache_name  = $this->cache_name;

			if(!empty($result['messages'][0]['message']) and ($result['messages'][0]['message'] == 'OK')) {

				$this->result = [];
				$result = $result['response']['data'];

				foreach($result as $record) {

					$row = new StdClass;

					// transpose repition fields.
					foreach($record['fieldData'] as $field => $value) {
						// $row->{$field} = $value;
						$field = str_replace('::', '____', $field);
						if(strpos($field, '(') !== FALSE) {
							$num = explode('(', $field);
							$num = end($num);
							$num = str_replace(')', '', $num);

							$field = str_replace('('.$num.')', '', $field);

							if(empty($row->{$field})) {
								$row->{$field} = array();
							}

							$row->{$field}[$num] = $value;
						} else {
							$row->{$field} = $value;
						}
					}
					$row->id = $record['recordId'];

					$this->result[$row->id] = $row;

				}


				if(!empty($this->cache_hours) and !empty($this->cache_name)) {

					$cache_file = $this->sanatise_cache($this->cache_name ,'_', TRUE);
					$cache_path = $this->cache_path . $cache_file .'.json';

					file_put_contents($cache_path, json_encode($this->result));

				}

				$this->_reset();

				return $this;

			}

			$this->errors[] = $result['messages'][0]['message'];

			if(!empty($this->errors)) {

				// echo '<pre>'; print_r(['query error:', $this->errors]); die('<br><br>File: '. __FILE__ .'<br>Line: '. __LINE__);

			}

			return FALSE;

		}


		private function sanatise_cache($file_name = NULL)
		{

			// if empty - return a random filename
			if(empty($file_name)) return uniqid() . time();

			$filename_bad_chars = [
				'../', '<!--', '-->', '<', '>',
				"'", '"', '&', '$', '#',
				'{', '}', '[', ']', '=',
				';', '?', '%20', '%22',
				'%3c',		// <
				'%253c',	// <
				'%3e',		// >
				'%0e',		// >
				'%28',		// (
				'%29',		// )
				'%2528',	// (
				'%26',		// &
				'%24',		// $
				'%3f',		// ?
				'%3b',		// ;
				'%3d',		// =
				'./',
				'/',
			];

			$non_displayables = [];
			$non_displayables[] = '/%0[0-8bcef]/i';	// url encoded 00-08, 11, 12, 14, 15
			$non_displayables[] = '/%1[0-9a-f]/i';	// url encoded 16-31
			$non_displayables[] = '/%7f/i';	// url encoded 127
			$non_displayables[] = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S';	// 00-08, 11, 12, 14-31, 127

			do {
				$file_name = preg_replace($non_displayables, '', $file_name, -1, $count);
			} while ($count);

			do {
				$old = $file_name;
				$file_name = str_replace($filename_bad_chars, '', $file_name);
			} while ($old !== $file_name);

			return stripslashes($file_name);
		}


		private function is_cached()
		{

			if(empty($this->cache)) return FALSE;
			if(empty($this->cache_name)) return FALSE;

			$cache_name = $this->sanatise_cache($this->cache_name ,'_', TRUE) .'.json';

			$this->cache_name = FALSE;

			if(!file_exists($this->cache_path . $cache_name)) {
				return FALSE;
			}

			if($this->cache_bust === TRUE) {
				unlink($this->cache_path . $cache_name);
				return FALSE;
			}

			$filemtime = filemtime($this->cache_path . $cache_name);

			if(time() - $filemtime > $this->cache) {
				unlink($this->cache_path . $this->cache_name);
				return FALSE;
			}

			$cached_json = file_get_contents($this->cache_path . $cache_name);
			$cached_json = json_decode($cached_json);

			$this->result = $cached_json;

			return $this;

		}



































































		// FROM HERE  - THIS IS THE RESTMP.PHP CLASS BY SO SIMPLE SOFTWARE

		public function productInfo () {
			if ($this->fmversion < 18) return $this->throwRestError (-1, "This public function is not supported in FileMaker 17");
			$url = "https://" . $this->host . "/fmi/data/".$this->version."/productInfo";
			$result = $this->callCURL ($url, 'GET');
			$this->updateDebug ('productInfo result', $result);
			return $result;
		}

		public function databaseNames () { //doesn't work when you're logged in (because both basic & bearer headers are sent)
			if ($this->fmversion < 18) return $this->throwRestError (-1, "This public function is not supported in FileMaker 17");
			$url = "https://" . $this->host . "/fmi/data/".$this->version."/databases";
			$header = "Authorization: Basic " . base64_encode ($this->user . ':' . $this->pass);
			$result = $this->callCURL ($url, 'GET', array(), array ($header));
			$this->updateDebug ('databaseNames result', $result);
			return $result;
		}

		public function layoutNames () {
			if ($this->fmversion < 18) return $this->throwRestError (-1, "This public function is not supported in FileMaker 17");
			$login = $this->login();
			if (!$this->checkValidLogin($login)) return $login;

			$url = "/layouts/";
			$result = $this->callCURL ($url, 'GET');
			$this->updateDebug ('layoutNames result pass 1', $result);

			$result = $this->checkValidResult($result);
			if (!$result){
				$result = $this->callCURL ($url, 'GET');
				$this->updateDebug ('layoutNames result pass 2', $result);
			}

			return $result;
		}

		public function scriptNames () {
			if ($this->fmversion < 18) return $this->throwRestError (-1, "This public function is not supported in FileMaker 17");
			$login = $this->login();
			if (!$this->checkValidLogin($login)) return $login;

			$url = "/scripts/";
			$result = $this->callCURL ($url, 'GET');
			$this->updateDebug ('scriptNames result pass 1', $result);

			$result = $this->checkValidResult($result);
			if (!$result) {
				$result = $this->callCURL ($url, 'GET');
				$this->updateDebug ('scriptNames result pass 2', $result);
			}
			return $result;
		}

		public function layoutMetadata ( $layout = NULL ) {
			if ($this->fmversion < 18) return $this->throwRestError (-1, "This public function is not supported in FileMaker 17");
			if (empty ($layout)) $layout = $this->layout;

			$login = $this->login();
			if (!$this->checkValidLogin($login)) return $login;

			$url = "/layouts/". rawurlencode($layout);
			$result = $this->callCURL ($url, 'GET');
			$this->updateDebug ('layoutMetadata result pass 1', $result);

			$result = $this->checkValidResult($result);
			if (!$result){
				$result = $this->callCURL ($url, 'GET');
				$this->updateDebug ('layoutMetadata result pass 2', $result);
			}
			return $result;
		}

		public function oldLayoutMetadata ( $layout = NULL ) {
			if ($this->fmversion < 18) return $this->throwRestError (-1, "This public function is not supported in FileMaker 17");
			if (empty ($layout)) $layout = $this->layout;

			$login = $this->login();
			if (!$this->checkValidLogin($login)) return $login;

			$url = "/layouts/". rawurlencode($layout) . "/metadata";
			$result = $this->callCURL ($url, 'GET');
			$this->updateDebug ('oldLayoutMetadata pass 1', $result);

			$result = $this->checkValidResult($result);
			if (!$result) {
				$result = $this->callCURL ($url, 'GET');
				$this->updateDebug ('oldLayoutMetadata pass 2', $result);
			}

			return $result;
		}

		public function createRecord ($data, $layout=NULL) {
			if (empty ($layout)) $layout = $this->layout;
			$login = $this->login();
			if (!$this->checkValidLogin($login)) return $login;

			$url = "/layouts/" . rawurlencode($layout) . "/records" ;
			$result = $this->callCURL ($url, 'POST', $data);

			$this->updateDebug ('create record data : ', $data);
			$this->updateDebug ('createRecord pass 1', $result);

			$result = $this->checkValidResult($result);
			if (!$result){
				$result = $this->callCURL ($url, 'POST', $data);
				$this->updateDebug ('createRecord pass 2', $result);
			}

			return $result; //error, foundcount, json and array
		}

		public function deleteRecord ($id, $scripts, $layout=NULL) {
			if (empty ($layout)) $layout = $this->layout;
			$login = $this->login();
			if (!$this->checkValidLogin($login)) return $login;

			$url = "/layouts/" . rawurlencode($layout) . '/records/' . $id ;
			$result = $this->callCURL ($url, 'DELETE',  $scripts);

			$this->updateDebug ('deleteRecord ' . $id . ' pass 1', $result);
			$result = $this->checkValidResult($result);
			if (!$result) {
				$result = $this->callCURL ($url, 'DELETE', $scripts);
				$this->updateDebug ('deleteRecord ' . $id . ' pass 2', $result);
			}
			return $result; //error
		}

		public function editRecord ($id, $record, $layout=NULL) {
			if (empty ($layout)) $layout = $this->layout;
			$login = $this->login();


			$this->updateDebug ('login login login ', $login);
			if (!$this->checkValidLogin($login)) return $login;

			$url = "/layouts/" . rawurlencode($layout) . '/records/' . $id ;
			$this->updateDebug ('update url ', $url);
			$result = $this->callCURL ($url, 'PATCH', $record);

			$this->updateDebug ('update record data ' . $id . ': ', $record);
			$this->updateDebug ('editRecord ' . $id . ' pass 1', $result);

			$result = $this->checkValidResult($result);
			if (!$result) {
				$result = $this->callCURL ($url, 'PATCH', $record);
				$this->updateDebug ('editRecord ' . $id . ' pass 2', $result);
			}

			return $result; //error, foundcount, json and array
		}

		public function getRecord ($id, $parameters= array (), $layout=NULL) {
			if (empty ($layout)) $layout = $this->layout;
			$login = $this->login();
			if (!$this->checkValidLogin($login)) return $login;

			$url = "/layouts/" . rawurlencode($layout) . '/records/' . $id ;
			$result = $this->callCURL ($url, 'GET', $parameters);

			$this->updateDebug ('getRecord ' . $id . ' pass 1', $result);

			$result = $this->checkValidResult($result);
			if (!$result) {
				$result = $this->callCURL ($url, 'GET', $parameters);
				$this->updateDebug ('getRecord ' . $id . ' pass 2', $result);
			}
			return $result; //error, foundcount, json and array
		}



		public function executeScript ( $scriptName, $scriptParameter, $layout=NULL ) {
			if ($this->fmversion < 18) return $this->throwRestError (-1, "This public function is not supported in FileMaker 17");
			if (empty ($layout)) $layout = $this->layout;
			$login = $this->login();
			if (!$this->checkValidLogin($login)) return $login;

			$url = "/layouts/" . rawurlencode($layout) . '/script/' . rawurlencode($scriptName);
			$parameters['script.param'] = $scriptParameter;
			$result = $this->callCURL ($url, 'GET', $parameters);

			$this->updateDebug ('executeScript ' . $scriptName . ' pass 1', $result);

			$result = $this->checkValidResult($result);
			if (!$result) {
				$result = $this->callCURL ($url, 'GET', $parameters);
				$this->updateDebug ('executeScript ' . $scriptName . ' pass 2', $result);
			}
			return $result; //error, foundcount, json and array
		}

		public function getRecords ($parameters=array(), $layout=NULL) {
			if (empty ($layout)) $layout = $this->layout;
			$login = $this->login();
			if (!$this->checkValidLogin($login)) return $login;

			$url = "/layouts/" . rawurlencode($layout) . "/records";
			$result = $this->callCURL ($url, 'GET', $parameters);

			$this->updateDebug ('getRecords pass 1',$result);

			$result = $this->checkValidResult($result);
			if (!$result) {
				$result = $this->callCURL ($url, 'GET', $parameters);
				$this->updateDebug ('getRecords pass 2',$result);
			}

			return $result; //error, foundcount, json and array
		}

		public function uploadContainer ($id, $fieldName, $file, $repetition = 1, $layout=NULL) {
			if (empty ($layout)) $layout = $this->layout;
			$login = $this->login();
			if (!$this->checkValidLogin($login)) return $login;

			$url = "/layouts/" . rawurlencode($layout) . '/records/' . $id . '/containers/' . rawurlencode($fieldName) . '/' . $repetition ;
			$cfile = curl_file_create($file['tmp_name'], $file['type'], $file['name']);
			$file = array ('upload' => $cfile);

			$result = $this->callCURL ($url, 'POSTFILE', $file);

			$this->updateDebug ('file ', $file);
			$this->updateDebug ('uploadContainer ' . $id . ' pass 1', $result);
			$result = $this->checkValidResult($result);
			if (!$result) {
				$result = $this->callCURL ($url, 'POSTFILE', '', $file);
				$this->updateDebug ('uploadContainer ' . $id . ' pass 2', $result);
			}
			return $result; //error
		}

		public function findRecords ($data, $layout=NULL) {
			if (empty ($layout)) $layout = $this->layout;
			$login = $this->login();
			if (!$this->checkValidLogin($login)) return $login;

			$url = "/layouts/" . rawurlencode($layout) . "/_find";
			$result = $this->callCURL ($url, 'POST', $data);

			$this->updateDebug ('findRecords pass 1' , $result);

			$result = $this->checkValidResult($result);
			if (!$result) {
				$result = $this->callCURL ($url, 'POST', $data);
				$this->updateDebug ('findRecords pass 2' , $result);
			}

			return $result;
		}

		public function setGlobalFields ($fields) {
			$login = $this->login();
			if (!$this->checkValidLogin($login)) return $login;

			$url =  "/globals" ;
			$result = $this->callCURL ($url, 'PATCH', $fields);

			$this->updateDebug ('setGlobalFields pass 1', $result);

			$result = $this->checkValidResult($result);
			if (!$result) {
				$result = $this->callCURL ($url, 'PATCH', $fields);
				$this->updateDebug ('setGlobalFields pass 1', $result);
			}
			return $result; //error, foundcount, json and array
		}


		public function login () {
			$this->updateDebug ('login start cookie',$_COOKIE);
			if (!empty ($_COOKIE[$this->token_name])) {
				$this->updateDebug ('login existing token', $_COOKIE[$this->token_name]);
				return (array('response'=> array ('token'=>$_COOKIE[$this->token_name]),'messages' => [array('code'=>0,'message'=>'Already have a token.')]));
			}

			$url =  "/sessions" ;
			$header = "Authorization: Basic " . base64_encode ($this->user . ':' . $this->pass);
			$result = $this->callCURL ($url, 'POST', array(), array ($header));
			$this->updateDebug ('login result',$result);

			if (isset ($result['response']['token'])) {
				$token = $result['response']['token'];
				setcookie($this->token_name, $token, time()+(14*60), '','',true,true);
				$_COOKIE[$this->token_name] = $token;
			}

			$this->updateDebug ('login end cookie',$_COOKIE);
			return $result;

		}

		public function logout ( $token = NULL ) {
			if (empty ($token)) $token = $_COOKIE[$this->token_name];

			if (empty ($token)) {
				$this->updateDebug ('logout no token');
				return ($this->throwRestError(0,'No Token'));
			}

			$url = "/sessions/" . $token ;
			$result = $this->callCURL ($url, 'DELETE');

			$this->updateDebug ('logout result', $result);

			if ($token == $_COOKIE[$this->token_name]) {
				setcookie($this->token_name, '');
				$_COOKIE [$this->token_name]='';
			}
			return $result;
		}


		public function callCURL ($url, $method, $payload='', $header=array()) {
			if ( substr ($url, 0, 4) != 'http') $url = "https://" . $this->host . "/fmi/data/".$this->version."/databases/" . rawurlencode($this->db) . $url;

			$this->updateDebug ("pre-payload: ", $payload);

			if ($method == 'POSTFILE') $contentType = 'multipart/form-data';
			else $contentType = 'application/json';

			$ch = curl_init();

			curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);


			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);         //follow redirects
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);         //return the transfer as a string
			if ($this->secure)  {
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);         //verify SSL CERT
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);         //verify SSL CERT
			} else {
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);         //don't verify SSL CERT
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);         //don't verify SSL CERT
			}
			curl_setopt($ch, CURLOPT_VERBOSE, 1);
			curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1); //Don'T use cache

			$auth_header = preg_grep('/^Authorization/i', $header);

			if (!empty($_COOKIE[$this->token_name]) && empty($header)) {
				$this->updateDebug ('not empty token on call', $_COOKIE[$this->token_name]);
				$header = array_merge ($header, array ('Authorization:Bearer '. $_COOKIE[$this->token_name] , 'Content-Type: '.$contentType));
				curl_setopt ($ch, CURLOPT_HTTPHEADER, $header );
			} else {
				$header = array_merge ($header, array ('Content-Type: '.$contentType));
				curl_setopt ($ch, CURLOPT_HTTPHEADER, $header);
			}


			$this->updateDebug ("payload: ", $payload);

			if ( isset ($payload) && is_array($payload)) {
				if ($method == 'GET' || $method == 'DELETE') {
					$url = $url . '?' . http_build_query($payload);
					unset ($payload);
				} elseif ($method != 'POSTFILE') {
					if (empty($payload))$payload = json_encode ($payload, JSON_FORCE_OBJECT);
					else $payload = json_encode ($payload) ;
				}

			}

			if ( isset ($payload))curl_setopt($ch, CURLOPT_POSTFIELDS, $payload );


			if ($method == 'POSTFILE') $method = 'POST';
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
			curl_setopt($ch, CURLOPT_URL, $url);

			$result = curl_exec($ch);
			$error = curl_error ($ch);
			$info = curl_getinfo ($ch);

			 curl_close($ch);
			 $this->updateDebug ('header', $header);

			 $this->updateDebug ('url', $url);
			 $this->updateDebug ("call error: ", $error);
			 $this->updateDebug ("call result: ", $result);
			 $this->updateDebug ("call info: ", $info);

			if (! empty ($result)) {
				$result = json_decode ($result, true);
				return $result;
			}
			elseif ( ! empty ($info['http_code'])) $this->throwRestError($info['http_code'],'HTTP Error '.$info['http_code']);
			elseif ( ! empty ($error)) return $this->throwRestError(-1,$error);
			else return $this->throwRestError(-1,'Empty Result');
		}

		public function throwRestError ($num,$message) {
			return (array ('response'=> array(), 'messages' => [array('code'=>$num,'message'=>$message)]));
		}

		public function checkValidResult($result){
			if ( isset($result['messages'][0]['code']) &&  $result['messages'][0]['code'] != 0 ) {
				$_COOKIE [$this->token_name]='';
				$login = $this->login();
				if ( $login['messages'][0]['code'] != 0) {
					$this->updateDebug ('checkValidResult', '2nd login failed');
					return $login;
				}
				$this->updateDebug ('checkValidResult', '2nd login succeeded');
				return false;
			}
			$this->updateDebug ('checkValidResult', 'valid result');
			return $result;
		}

		public function checkValidLogin($result){
			if ( isset($result['messages'][0]['code']) &&  $result['messages'][0]['code'] != 0 ) { //any error in result
				$this->updateDebug ('Failed initial login', $result);
				return false;
			}
			$this->updateDebug ('Succeeded initial login', $result);
			return true;
		}


		public function __destruct() {
			if (strtoupper ($this->show_debug) == "HTML") {
				echo "<br><strong>DEBUGGING ON: </strong><br>";
				echo "<pre>";
				print_r ($this->debug_array);
				echo "</pre>";
			}
			elseif ($this->show_debug) {
				echo "\nDEBUGGING ON: \n";
				print_r ($this->debug_array);
			}
		}

		public function updateDebug ($label, $value = '') {
				$this->debug_array[$label] = $value;
		}
	}
