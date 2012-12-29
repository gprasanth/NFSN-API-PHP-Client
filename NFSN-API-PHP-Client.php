<?php

class NFSN_API_PHP_Client {

	private $base_url = "https://api.nearlyfreespeech.net";

	private $auth_header_name = "X-NFSN-Authentication";

	private $login;

	private $api_key;

	public function __construct($login, $api_key)
	{
		$this->login = $login;
		$this->api_key = $api_key;
	}

	// 16 character random string
	private function salt()
	{
		// append 'rnd' to beginning of 13 character random string to make the length 16
		return uniqid("rnd");
	}

	/**
	 * Retrieve time from an NTP server
	 *
	 * @param    string   $host   The NTP server to retrieve the time from
	 * @return   int      The current unix timestamp
	 * @author   Aidan Lister <aidan@php.net>
	 * @link     http://aidanlister.com/2010/02/retrieve-time-from-an-ntp-server/
	 */
	private function ntp_time($host)
	{
		// Create a socket and connect to NTP server
		$sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
		socket_connect($sock, $host, 123);

		// Send request
		$msg = "\010" . str_repeat("\0", 47);
		socket_send($sock, $msg, strlen($msg), 0);

		// Receive response and close socket
		socket_recv($sock, $recv, 48, MSG_WAITALL);
		socket_close($sock);

		// Interpret response
		$data = unpack('N12', $recv);
		$timestamp = sprintf('%u', $data[9]);

		// NTP is number of seconds since 0000 UT on 1 January 1900
		// Unix time is seconds since 0000 UT on 1 January 1970
		$timestamp -= 2208988800;

		return $timestamp;
	}

	// Compute the value for X-NFSN-Authentication header
	private function get_auth_header($url, $body)
	{
		// pick any ntp server you prefer
		$timestamp = $this->ntp_time('0.us.pool.ntp.org');

		// generate the 16 character salt
		$salt = $this->salt();

		// if no body, use "" (empty string)
		if (empty($body))
			$body = "";
		$body_hash = sha1($body);

		// login;timestamp;salt;api_key;url;body_hash
		$hash_string = $this->login . ";" . $timestamp . ";" . $salt . ";" . $this->api_key . ";" . $url . ";" . $body_hash;

		$hash = sha1($hash_string);

		// login;timestamp;salt;hash
		return $this->login . ";" . $timestamp . ";" . $salt . ";" . $hash;
	}

	// wrapper for get
	public function get($url)
	{
		return $this->do_send("get", $url);
	}

	// wrapper for put
	public function put($url, $body)
	{
		return $this->do_send("put", $url, $body, "text/plain");
	}

	// wrapper for post
	public function post($url, $params)
	{
		$body = http_build_query($params);
		return $this->do_send("post", $url, $body, "");
	}

	// make cURL requests
	private function do_send($method, $url, $body, $mimetype)
	{
		// actual request url is base_url + url !
		$request = curl_init($this->base_url . $url);

		// ssl verification
		curl_setopt($request, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($request, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($request, CURLOPT_CAINFO, get_cwd()."/nfsn-ca.crt");

		// set our useragent
		curl_setopt($request, CURLOPT_USERAGENT, "NFSN_API_PHP_Client/1.0");

		curl_setopt($request, CURLOPT_RETURNTRANSFER, TRUE);

		// headers array - use array_push for creating headers
		$headers = array();

		// set appropriate options
		switch($method)
		{
			case "put":
				array_push($headers, 'X-HTTP-Method-Override: PUT');
				break;
			case "post":
				curl_setopt($request, CURLOPT_POST, TRUE);
				break;
			case "get":
				curl_setopt($request, CURLOPT_HTTPGET, TRUE);
				break;
			default:
				curl_setopt($request, CURLOPT_HTTPGET, TRUE);
		}

		// set the header required for NFSN API to work
		array_push($headers, $this->auth_header_name." : ".$this->get_auth_header($url, $body));

		// set post data - in case of post and put requests
		if (!empty($body))
		{
			curl_setopt($request, CURLOPT_POSTFIELDS, $body);
		}

		// send the headers
		curl_setopt($request, CURLOPT_HTTPHEADER, $headers);

		$response = curl_exec($request);

		// get response code
		$info = curl_getinfo($request, CURLINFO_HTTP_CODE);

		curl_close($request);

		// if code == 200, success. else, check $response (json string!)
		return array('code'=>$info, 'response'=>$response);
	}

}


?>
