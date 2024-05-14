<?php

	/**
	 * FileMaker-Data-API-PHP-Wrapper
	 * Sheldon Lendrum
	 */
	class Fmapi {

		public $host = '';
		public $db   = '';
		public $user = '';
		public $pass = '';

		public $version = 'vLatest';
		public $fm_version = 20;
		public $layout = '';
		public $result = [];

		public $where    = [];
		public $order_by = [];
		public $offset   = [];
		public $limit    = [];

		public $timeout = 8;

		public $secure = TRUE;
		public $token_name = 'fm_token';
		public $show_debug = FALSE; //'html';

		public $queries = [];
		public $errors = [];
		public $debug_array = [];

		/**
		 * Caching setup;
		 */
		 public $cache_hours = 0;
		 public $cache_object = [];
		 public $cache_bust = FALSE;
		 public $cache = FALSE; 
 
		 /**
		  * Supported caching methods:
		  * - 'file'
		  * - 'mongodb'
		  */
		 public $cache_type = FALSE; 
 
		 /**
		  * For file based caching, 
		  * eg: '../storage/';
		  */
		 public $cache_path = FALSE; 


        /**
         * MongoDB Database connection string
         * only required for Mongo connections.
         */
        protected $cache_mongodb_dsn = '';

		public function __construct()
		{
			$this->_reset();

			if(!empty($this->cache_type)) {

				if(file_exists('Fmapi_cache.php')) {
					require_once 'Fmapi_cache.php';

					if($this->cache_type == 'file') {
						$this->cache = new Fmapi_cache();
						$this->cache
							->type($this->cache_type)
							->path($this->cache_path);
					}

					if($this->cache_type == 'mongodb') {
						$this->cache = new Fmapi_cache();
						$this->cache
							->type($this->cache_type)
							->dsn($this->cache_mongodb_dsn);
					}

				}

			}
		}

		public function debug($show_debug = NULL)
		{
			$this->show_debug = $show_debug;
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

		public function host($host = NULL)
		{
			$this->host = $host;
			return $this;
		}

		public function pass($pass = NULL)
		{
			$this->pass = $pass;
			return $this;
		}

		public function timeout($timeout = NULL)
		{
			$this->timeout = (int)$timeout;
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
			$this->result = [];
			$this->layout = $layout;
			$this->_query();
			return $this;
		}

		public function update($layout, $update_data, $fm_id)
		{
			return $this->editRecord($fm_id, ['fieldData' => $update_data], $layout);
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

		public function offset($offset = 1)
		{
			$this->offset = $offset;
			return $this;

		}

		public function limit($limit = 1)
		{
			$this->limit = $limit;
			return $this;

		}

		public function row()
		{
			$this->_reset();
			if(empty($this->result)) return FALSE;
			return reset($this->result);

		}

		public function result()
		{
			$this->_reset();
			if(empty($this->result)) return FALSE;
			return $this->result;

		}


		public function container($container_url = NULL, $cache_name = NULL)
		{
			if(empty($container_url)) return FALSE;

			$cookie = tempnam(sys_get_temp_dir(), "CURLCOOKIE");

			$ch = curl_init($container_url);
			curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			if ($this->secure)  {
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
			} else {
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			}
			
			curl_exec($ch);

			if(curl_errno($ch)!==0){
				$error_message = curl_error($ch);
				$this->errors[] = 'Error in creating cookie file. '. $error_message;
				return FALSE;
			}

			$ch = curl_init($container_url);
			curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			if ($this->secure)  {
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
			} else {
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			}
			$file_data = curl_exec($ch);
			if(curl_errno($ch)!==0){
				$error_message = curl_error($ch);
				$this->errors[] = 'Error in downloading content of file. '. $error_message;
				return FALSE;
			}

			return $file_data;
		}


		public function reset()
		{
			$this->_reset();
			return $this;
		}

		private function _reset()
		{
			$this->where = [];
			$this->order_by = [];
			$this->offset = 1;
			$this->limit = 99999;
			$this->timeout = 30;
			$this->cache_hours = 0;
			$this->result = [];
			$this->cache_object = [];
			$this->cache_bust = FALSE;
		}

		private function _query()
		{

			$login = $this->login();
			if(!$this->checkValidLogin($login)) return $login;

			$data = [];

			if(empty($this->where)) {
				
				$order_string = '';
				if(!empty($this->order_by)) {
					$sort = [];
					foreach($this->order_by as $f => $d) {
						$order_string .= "{$f}_{$d}__";
						$sort[] = [
							'fieldName' => $f,
							'sortOrder' => ($d == 'ASC' ? 'ascend' : 'descend'),
						];
					}
					$data['sort'] = $sort;
					$order_string = rtrim($order_string, '_');
					$order_string = rtrim($order_string, '_');
					$order_string = trim($order_string);
				}

				$this->cache_object = [
					'from' => $this->layout,
					'where' => 'all',
					'orderby' => $order_string,
					'offset' => (int)$this->offset,
					'limit' => (int)$this->limit,
				];



				if($this->is_cached()) {
					return $this;
				}

				$data['_offset'] = (int)$this->offset;
				$data['_limit'] = (int)$this->limit;

				$this->queries['fm'][] = $this->cache_object;

				$result = $this->getRecords($data, $this->layout);
			} else {

				$where_string = '';
				foreach($this->where as $f => $v) {
					$where_string .= "{$f}_{$v}__";
				}
				$where_string = rtrim($where_string, '_');
				$where_string = rtrim($where_string, '_');
				$where_string = trim($where_string);

				$query = [$this->where];

				$order_string = '';
				if(!empty($this->order_by)) {
					$sort = [];
					foreach($this->order_by as $f => $d) {
						$order_string .= "{$f}_{$d}__";
						$sort[] = [
							'fieldName' => $f,
							'sortOrder' => ($d == 'ASC' ? 'ascend' : 'descend'),
						];
					}
					$data['sort'] = $sort;
					$order_string = rtrim($order_string, '_');
					$order_string = rtrim($order_string, '_');
					$order_string = trim($order_string);
				}


				$this->cache_object = [
					'from' => $this->layout,
					'where' => $where_string,
					'orderby' => (!empty($order_string) ? $order_string : 'none'),
					'offset' => (int)$this->offset,
					'limit' => (int)$this->limit,
				];

				if($this->is_cached()) {
					return $this;
				}

				$last_query = "SELECT FROM `{$this->layout}` WHERE ";
				foreach($this->where as $f => $v) {
					$last_query .= " `{$f}` = '{$v}' AND ";
				}
				$last_query = rtrim($last_query, ' AND ');
				if(!empty($this->order_by)) {
					$last_query .= " ORDER BY ";
					foreach($this->order_by as $f => $d) {
						$last_query .= " `{$f}` {$d} ";
					}
				}
				$last_query .= " LIMIT  ". (int)$this->offset .', '. (int)$this->limit;
				
				$this->queries['fm'][] = $last_query;

				// $data['limit'] = (int)$this->limit;
				// $data['range'] = (int)$this->limit;
				$data['query'] = $query;
				$result = $this->findRecords($data, $this->layout);
			}

			if(!empty($result['messages'][0]['message']) and ($result['messages'][0]['message'] == 'OK' or $result['messages'][0]['code'] == '401')) {

				$this->result = [];
		
				if(!empty($result['response']['data'])) {
						
					$result = $result['response']['data'];

					foreach($result as $record) {

						$row = new StdClass;

						// transpose repetition fields.
						foreach($record['fieldData'] as $field => $value) {
							$field = str_replace('(', '__', $field);
							$field = str_replace(')', '', $field);
							$row->{$field} = $value;
						}
						$row->id = $record['recordId'];

						$this->result[$row->id] = $row;

					}
				}

				if(!empty($this->cache_hours) and !empty($this->cache_object)) {

					if($this->cache_type !== FALSE) {
						$this->cache
							->expires($this->cache_hours)
							->query($this->cache_object)
							->result($this->result)
							->store();
					}

				}

				$this->_reset();

				return $this;

			}

			$this->errors[] = $result; 

			if(!empty($this->errors)) {

			}

			return FALSE;

		}


		private function is_cached()
		{

			if(empty($this->cache_object)) return FALSE;


			if($this->cache_type !== FALSE) {
				
				$this->result = $this->cache
					->bust($this->cache_bust)
					->query($this->cache_object)
					->retrieve();
			}

			if(empty($this->result)) {
				$this->result = [];
			}

			return $this;

		}




































































	/*

		Based on
		https://github.com/kdoronzio/fmREST.php
		http://www.sosimplesoftware.com/fmrest.php


	*/
		public function productInfo () {
			if ($this->fm_version < 18) return $this->throwRestError (-1, "This public function is not supported in FileMaker 17");
			$url = "https://" . $this->host . "/fmi/data/".$this->version."/productInfo";
			$result = $this->callCURL ($url, 'GET');
			$this->updateDebug ('productInfo result', $result);
			return $result;
		}

		public function databaseNames () { //doesn't work when you're logged in (because both basic & bearer headers are sent)
			if ($this->fm_version < 18) return $this->throwRestError (-1, "This public function is not supported in FileMaker 17");
			$url = "https://" . $this->host . "/fmi/data/".$this->version."/databases";
			$header = "Authorization: Basic " . base64_encode ($this->user . ':' . $this->pass);
			$result = $this->callCURL ($url, 'GET', array(), array ($header));
			$this->updateDebug ('databaseNames result', $result);
			return $result;
		}

		public function layoutNames () {
			if ($this->fm_version < 18) return $this->throwRestError (-1, "This public function is not supported in FileMaker 17");
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
			if ($this->fm_version < 18) return $this->throwRestError (-1, "This public function is not supported in FileMaker 17");
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
			if ($this->fm_version < 18) return $this->throwRestError (-1, "This public function is not supported in FileMaker 17");
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
			if ($this->fm_version < 18) return $this->throwRestError (-1, "This public function is not supported in FileMaker 17");
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
			if ($this->fm_version < 18) return $this->throwRestError (-1, "This public function is not supported in FileMaker 17");
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
