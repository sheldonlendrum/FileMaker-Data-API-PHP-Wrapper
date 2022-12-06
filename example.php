<?php

	// VANILLA PHP
	require_once 'Fmapi.php';


	// You only need to do this once in your script.
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


	// get a fresh copy of this user, and cache then cache valid result for 24 hours
	$user = $db
		->bust(TRUE)
		->cache(24)
		->where('username', 'sheldon')
		->get('users')
		->row();
		// single object or FALSE


	// Display a users avatar fomr a container field.
	$avatar = $db->container($user->avatar_container);

	if(!empty($avatar)) {

		// render display
		if(strpos($user->avatar_container, '.jpeg') !== FALSE) header('Content-type: image/jpeg');
		if(strpos($user->avatar_container, '.jpg') !== FALSE) header('Content-type: image/jpeg');
		if(strpos($user->avatar_container, '.png') !== FALSE) header('Content-type: image/png');
		if(strpos($user->avatar_container, '.gif') !== FALSE) header('Content-type: image/gif');
		echo $avatar;
		die;


		// display inline
		echo '<imgh src="data:image/jpeg;base64,'. base64_encode($avatar) .'" alt="'. $user->first_name .'" />';
	}




	// Code Igniter 3
	// Copy Fmapi.php to yuor application/libraries Directory
	$this->load->library('fmapi');
	$this->load->library('form_validation');

	$this->fmapi
		->user('fm_data_api_username')
		->pass('secure_password')
		->host('fm_server_dns_or_ip_address')
		->db('my_layout_name')
		->cache_path('./cache_dir/');

	$this->form_validation->set_rules('username', 'Username', 'trim|required');
	$this->form_validation->set_rules('first_name', 'First Name', 'trim|required');
	$this->form_validation->set_rules('last_name', 'Last Name', 'trim|required');
	$this->form_validation->set_rules('email', 'Email Address', 'trim|required|valid_email');
	$this->form_validation->set_rules('password', 'Secure Password', 'trim|required|matches[confirm_password]|min_length[8]');

	if($this->form_validation->run()) {

		$this->fmapi->insert('users', [
			'username' => $this->input->post('username'),
			'first_name' => $this->input->post('first_name'),
			'last_name' => $this->input->post('last_name'),
			'email' => $this->input->post('email'),
			'password' => sha1($this->input->post('password')),
		]);

	}

	// unchained query
	$this->fmapi->reset();
	$this->fmapi->cache(4);
	if(!empty($username)) {
		$this->fmapi->where('username', $username);
	}
	if(!empty($email)) {
		$this->fmapi->where('email', $email);
	}
	$this->fmapi->get('users');
	$users = $this->fmapi->result();
