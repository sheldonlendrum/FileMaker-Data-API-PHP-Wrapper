
#  FileMaker-Data-API-PHP-Wrapper

This PHP class wraps the RestFM.php class by https://www.sosimplesoftware.com

This was developed to by used in a [CodeIgniter](https://codeigniter.com/) 3 project, to replace the default mySQL database calls as closely as possible, making the queries as database agnostic as possible.

The query builder syntax supports method chaining or simple queries.
it also supports local file based caching.

## Basic setup queries
```
	$db = new Fmapi();
	$db->user('fm_data_api_username')
		->pass('secure_password')
		->host('fm_server_dns_or_ip_address')
		->db('my_layout_name');

	$users = $db->get('users')
		->result();
		// array of objects or FALSE


	$user = $db
		->where('username', 'sheldon')
		->get('users')
		->row();
		// single object or FALSE
```



## Query caching
```
	$db = new Fmapi();
	$db->user('fm_data_api_username')
		->pass('secure_password')
		->host('fm_server_dns_or_ip_address')
		->db('my_layout_name')
		->cache_path('./cache_dir/');

	// get and cache a list of users for 4 hours
	$users = $db->get('users')
		->cache(4)
		->result();
		// array of objects or FALSE


	// get a fresh copy of this user, and cache for 24 hours
	$user = $db
		->bust(TRUE)
		->cache(24)
		->where('username', 'sheldon')
		->get('users')
		->row();
		// single object or FALSE
```


## Inserting data
```

	$db->insert('users', [
		'username' => 'rob',
		'first_name' => 'Robert',
		'last_name' => 'Jones',
		'email' => 'rob@robertjones.nz',
		'password' => sha1('$ecurePassW0rd'),
	]);

```

## Updating data
```
	$user = $db
		->bust(TRUE)
		->where('username', 'sheldon')
		->get('users')
		->row();

	$db->update('users', [
		'password' => sha1('New$ecurePassW0rd'),
	], $user->id); // ->id is ALWAYS the FM Record ID.

```



##  Original Class by So Simple Software

Based on
https://github.com/kdoronzio/fmREST.php
http://www.sosimplesoftware.com/fmrest.php