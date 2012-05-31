<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Pushko (Push[er]ko[hana])
 * This is a very simple PHP library for Kohana to the Pusher API
 *
 *    $pusher = Pushko::factory(CHANNEL, [config_name]);
 *    $pusher->trigger('event_name', 'data', [$socket_id = NULL, $channel = '', $debug = FALSE]);
 *    $pusher->socket_auth('socket_id');
 *
 * @author	2bj
 * @author	Squeeks
 * @author	Paul44 (http://github.com/Paul44)
 * @author	Ben Pickles (http://github.com/benpickles)
 * @author	Mastercoding (http://www.mastercoding.nl)
 * @copyright	(c) 2010 2bj
 * @copyright	(c) 2010 Squeeks
 * @license	http://www.opensource.org/licenses/mit-license.php
 */
abstract class Pushko_Core {
	
	const API_AUTH_VERSION = '1.0';

	/**
	 * @var	string	Default config name
	 */
	public static $default_config_name = 'default';

	/**
	 * @var	array	Configuration array
	 */
	protected $_config;
	
	/**
	 * Create and return new Pusko object
	 * 
	 * @param string $channel
	 * @param string $name
	 * @return Pushko
	 */
	public static function factory($channel, $name = NULL)
	{
		return new Pushko($channel, $name);
	}

	/**
	 * Init Pushko
	 *
	 * @param string $channel
	 * @param string $name
	 */
	public function __construct($channel, $name = NULL)
	{
		if ($name === NULL)
		{
			$name = self::$default_config_name;
		}

		$this->_config = Kohana::$config->load('pushko')->$name;

		if ( ! isset($this->_config['app_id'])		OR
			   $this->_config['app_id'] === NULL	OR
			   $this->_config['app_id'] == ''
			)
		{
			throw new Pushko_Exception('Pusher app_id is not defined in :name configuration',
					array(':name' => $name));
		}

		if ( ! isset($this->_config['auth_key'])	OR
			   $this->_config['auth_key'] === NULL	OR
			   $this->_config['auth_key'] == ''
			)
		{
			throw new Pushko_Exception('Pusher auth_key is not defined in :name configuration',
					array(':name' => $name));
		}

		if ( ! isset($this->_config['secret'])		OR
			   $this->_config['secret'] === NULL	OR
			   $this->_config['secret'] == ''
			)
		{
			throw new Pushko_Exception('Pusher secret is not defined in :name configuration',
					array(':name' => $name));
		}

		$this->_config['channel'] = $channel;
		$this->_config['url'] = '/apps/' . $this->_config['app_id'];
	}

	/**
	 * Trigger an event by providing event name and payload.
	 * Optionally provide a socket ID to exclude a client (most likely the sender).
	 *
	 * @param	string	$event
	 * @param	mixed	$payload
	 * @param	integer	$socket_id [optional]
	 * @param	string	$channel [optional]
	 * @param	boolean	$debug [optional]
	 * @return	mixed
	 */
	public function trigger($event, $payload, $socket_id = NULL, $channel = '', $debug = FALSE)
	{

		// Check if we can initialize a cURL connection
		$ch = curl_init();
		if ($ch === FALSE)
		{
			throw new Pushko_Exception('Could not initialise cURL!');
		}

		// Add channel to URL..
		$s_url = $this->_config['url'].'/channels/'
				 .($channel != '' ? $channel : $this->_config['channel'])
				 .'/events';

		// Build the request
		$signature = "POST\n".$s_url."\n";
		$payload_encoded = json_encode($payload);
		$query = "auth_key=".$this->_config['auth_key']."&auth_timestamp="
			     .time()."&auth_version=".self::API_AUTH_VERSION."&body_md5="
				 .md5($payload_encoded)."&name=".$event;

		// Socket ID set?
		if ($socket_id !== NULL )
		{
			$query .= '&socket_id='.$socket_id;
		}

		// Create the signed signature...
		$auth_signature = hash_hmac('sha256', $signature.$query, $this->_config['secret'], FALSE);
		$signed_query = $query."&auth_signature=".$auth_signature;
		$full_url = $this->_config['server'].':'.$this->_config['port']
				    .$s_url.'?'.$signed_query;

		// Set cURL opts and execute request
		curl_setopt($ch, CURLOPT_URL, $full_url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $payload_encoded);
		curl_setopt($ch, CURLOPT_TIMEOUT, $this->_config['timeout']);

		$response = curl_exec($ch);

		curl_close($ch);

		if ($response == "202 ACCEPTED\n" && $debug == FALSE)
		{
			return TRUE;
		}
		elseif ($debug === TRUE || $this->_config['debug'] === TRUE)
		{
			return $response;
		}
		else
		{
			return FALSE;
		}

	}

	/**
	 * Creates a socket signature
	 *
	 * @param	integer	$socket_id
	 * @return	string
	 */
	public function socket_auth($socket_id)
	{
		$signature = hash_hmac(
			'sha256', $socket_id.':'.$this->_config['channel'],
			$this->_config['secret'],
			FALSE
		);
		$signature = array('auth' => $this->_config['auth_key'].':'.$signature);

		return json_encode($signature);
	}
}