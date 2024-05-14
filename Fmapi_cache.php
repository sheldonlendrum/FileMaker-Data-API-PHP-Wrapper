<?php

	/**
	 * FileMaker-Data-API-PHP-Wrapper
     * Data Caching extension
	 * Sheldon Lendrum
	 */
	class Fmapi_cache {


		/**
		 * Supported caching methods:
		 * - 'file'
		 * - 'mongodb'
		 */
        public $cache_type = 'file';

		/**
		 * cache path, only used for file based storage.
		 * eg: '../cache/';
		 */
        public $cache_path = '';


		/**
		 * How many hours to cache the result for.
		 */
        public $expires = 4;


		/**
		 * Force cache expiration and refresh the result
		 */
        public $cache_bust = FALSE;


		/**
		 * Query to create cache reference.
		 */
        public $query = '';


		/**
		 * Result to cache
		 */
        public $result = '';

        /**
         * MongoDB Database connection string
         * only required for Mongo connections.
         */
        protected $mongodb_dsn = '';


    	public function __construct()
		{

        }

		public function type($cache_type = NULL)
		{
			$this->cache_type = $cache_type;
			return $this;
		}

		public function path($cache_path = NULL)
		{
			$this->cache_path = $cache_path;
			return $this;
		}

		public function expires($expires = NULL)
		{
			$this->expires = $expires;
			return $this;
		}

		public function bust($cache_bust = NULL)
		{
			$this->cache_bust = $cache_bust;
			return $this;
		}

		public function query($query = NULL)
		{
			$this->query = $query;
			return $this;
		}

		public function result($result = NULL)
		{
			$this->result = $result;
			return $this;
		}

        /**
         * Set your Mongo connection dsn string.
         * * eg: 'mongodb://user:pass@127.0.0.1:27017/?ssl=true&replicaSet=replica&authSource=admin'
         */
		public function dsn($mongodb_dsn = NULL)
		{
			$this->mongodb_dsn = $mongodb_dsn;
			return $this;
		}

        /**
         * Store a cached result, or 401 (404) result.
         */
		public function store()
		{

            if(empty($this->expires)) return FALSE; 
            if(empty($this->query)) return FALSE; 
            if(empty($this->result)) return FALSE; 

            if($this->cache_type == 'file') {
                if(empty($this->cache_path)) {
                    if(!is_dir($this->cache_path)) {
                        $cache = new Fmapi_file_cache();
                        $cache->store($this->expires, $this->query, $this->result);
                    }
                }
            }

            if($this->cache_type == 'mongodb') {
                if(!empty($this->mongodb_dsn)) {
                    $cache = new Fmapi_mongodb_cache($this->mongodb_dsn);
                    $cache->store($this->expires, $this->query, $this->result);
                }
            }

            /**
             * Reset cache object. 
             */
			$this->expires = 4;
			$this->query = '';
			$this->result = '';
			$this->cache_bust = FALSE;
			return TRUE;
		}

        /**
         * Get for an existing, non-expired cached result.
         */
        public function retrieve()
        {
            if(empty($this->query)) return FALSE; 


            if($this->cache_type == 'file') {
                if(empty($this->cache_path)) {
                    if(!is_dir($this->cache_path)) {
                        $cache = new Fmapi_file_cache($this->cache_path);
                        return $cache->retrieve($this->query, $this->cache_bust);
                    }
                }
            }

            if($this->cache_type == 'mongodb') {
                if(!empty($this->mongodb_dsn)) {
                    $cache = new Fmapi_mongodb_cache($this->mongodb_dsn);
                    return $cache->retrieve($this->query, $this->cache_bust);
                }
            }
            
            return FALSE; 

        }

    }














    /**
     * File based storage. 
     */
    class Fmapi_file_cache {

        public $cache_path;

    	public function __construct($cache_path = NULL)
		{
            $this->cache_path = $cache_path;
        }

        public function store($cache_hours = NULL, $cache_object = NULL, $result = NULL)
        {
            if(empty($this->cache_path)) return FALSE; 
            if(!is_dir($this->cache_path))  return FALSE; 

            $cache_json = json_encode($cache_object);
			$cache_md5  = md5($cache_json) .'.cache';

            if(file_exists($this->cache_path . $cache_md5)) {
			    unlink($this->cache_path . $cache_md5);
            }

			$cache_object['hash'] = $cache_md5;
			$cache_object['data'] = $result;
            $cache_object['expires'] = strtotime('+ '. $cache_hours .' hours');

            /**
             * Cache a no records found result 
             * But only for a shorter time.
             */
			if(!empty($result['messages'][0]) and $result['messages'][0]['code'] == '401') {
				$cache_object['expires'] = strtotime('+ 30 minutes');
			}
            
		    file_put_contents($this->cache_path . $cache_md5, json_encode($cache_object));
        }

        public function retrieve($cache_object = NULL, $cache_bust = FALSE)
        {

            if(empty($this->cache_path)) return FALSE; 

            $cache_json = json_encode($cache_object);
			$cache_md5  = md5($cache_json) .'.cache';

            if(!file_exists($this->cache_path . $cache_md5)) {
			    return FALSE; 
            }

			if($cache_bust === TRUE) {
				unlink($this->cache_path . $cache_md5);
                return FALSE; 
			}

            $cache = file_get_contents($this->cache_path . $cache_md5);
            $cache = json_decode($cache);

			if(time() > $cache->expires) {
				unlink($this->cache_path . $cache_md5);
                return FALSE;
			}

			$cache->data = json_encode($cache->data);
			$cache->data = json_decode($cache->data, TRUE);

			$result = [];

			if(is_array($cache->data)) {

				foreach($cache->data as $index => $object) {

					$result[$index] = is_array($object) ? (object)$object : $object;

				}
				
			}

            return $result;
        }
        
    }















    


    /**
     * Mongodb Cache extension
     * You must set up your Mongo DB Connection
     */
    class Fmapi_mongodb_cache {

        public $mongodb;
        public $collection = 'cache';

    	public function __construct($mongodb_dsn = NULL)
		{
            $this->mongodb = new MongoDB\Client($mongodb_dsn);
        }

        public function store($cache_hours = NULL, $cache_object = NULL, $result = NULL)
        {

            $cache_json = json_encode($cache_object);
			$cache_md5  = md5($cache_json);

			$this->mongodb->{$this->collection}->deleteMany(['hash' => $cache_md5]);

			$cache_object['hash'] = $cache_md5;
			$cache_object['data'] = $result;
            $cache_object['expires'] = strtotime('+ '. $cache_hours .' hours');

            /**
             * Cache a no records found result 
             * But only for a shorter time.
             */
			if(!empty($result['messages'][0]) and $result['messages'][0]['code'] == '401') {
				$cache_object['expires'] = strtotime('+ 30 minutes');
			}
            
		    $this->mongodb->insert($this->collection, $cache_object);
        }

        public function retrieve($cache_object = NULL, $cache_bust = FALSE)
        {

			$cache_json = json_encode($cache_object);
			$cache_md5  = md5($cache_json);

			$cache = $this->mongodb->findOne($this->collection, ['hash' => $cache_md5]);
			if(empty($cache)) {
				return FALSE;
			}

			if($cache_bust === TRUE) {
				$this->mongodb->{$this->collection}->deleteOne($cache_object);
				return FALSE;
			}


			if(time() > $cache->expires) {
				
				$this->mongodb->{$this->collection}->deleteOne($cache_object);
				return FALSE;
			}

			$cache->data = json_encode($cache->data);
			$cache->data = json_decode($cache->data, TRUE);

			$result = [];

			if(is_array($cache->data)) {

				foreach($cache->data as $index => $object) {

					$result[$index] = is_array($object) ? (object)$object : $object;

				}
				
			}

            return $result;
        }


    }