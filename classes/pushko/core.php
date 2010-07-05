<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Pushko (Push[er]ko[hana])
 * This is a very simple PHP library for Kohana to the Pusher API
 *
 *    $pusher = new Pushko(APIKEY, SECRET, APP_ID, CHANNEL, [Debug: TRUE/FALSE, HOST, PORT]);
 *    $pusher->trigger('event_name', 'channel_name', [socket_id, Debug: TRUE/FALSE]);
 *    $pusher->socket_auth('socket_id');
 *
 * @author		2bj
 * @author	    Squeeks
 * @author		Paul44 (http://github.com/Paul44)
 * @author		Ben Pickles (http://github.com/benpickles)
 * @author		Mastercoding (http://www.mastercoding.nl)
 * @copyright  (c) 2010 2bj
 * @copyright  (c) 2010 Squeeks
 * @license     http://www.opensource.org/licenses/mit-license.php
 */
abstract class Pushko_Core {

	/**
	 * @var	array Settings
	 */
	private $settings = array ();

	/**
	 * Create a new Pushko object.
	 *
	 * Initializes a new Pushko instance with key, secret , app ID and channel.
	 * You can optionally turn on debugging for all requests by setting debug to true.
	 *
	 * @param	string	$auth_key
	 * @param	string	$secret
	 * @param	integer	$app_id
	 * @param	string	$channel
	 * @param	boolean	$debug
	 * @param	string	$host
	 * @param	integer	$port
	 * @param	integer	$timeout
	 */
	public function __construct(
			$auth_key, $secret, $app_id, $channel = '',
			$debug = FALSE, $host = 'http://api.pusherapp.com',
			$port = '80', $timeout = 30 )
	{

		// Check compatibility, disable for speed improvement
		$this->check_compatibility();

		// Setup defaults
		$this->settings['server']	= $host;
		$this->settings['port']		= $port;
		$this->settings['auth_key']	= $auth_key;
		$this->settings['secret']	= $secret;
		$this->settings['app_id']	= $app_id;
		$this->settings['channel']	= $channel;
		$this->settings['url']		= '/apps/' . $this->settings['app_id'];
		$this->settings['debug']	= $debug;
		$this->settings['timeout']	= $timeout;

	}

	/**
	* Check if the current PHP setup is sufficient to run this class
	*/
	private function check_compatibility()
	{

		// Check for dependent PHP extensions (JSON, cURL)
		if ( ! extension_loaded('curl') || ! extension_loaded('json'))
		{
			throw new Pushko_Exception('There is missing dependant extensions - please ensure both cURL and JSON modules are installed');
		}

		// Supports SHA256?
		if ( ! in_array('sha256', hash_algos()))
		{
			throw new Pushko_Exception('SHA256 appears to be unsupported - make sure you have support for it, or upgrade your version of PHP.');
		}
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
	public function trigger( $event, $payload, $socket_id = NULL, $channel = '', $debug = FALSE )
	{

		// Check if we can initialize a cURL connection
		$ch = curl_init();
		if ($ch === FALSE)
		{
			throw new Pushko_Exception('Could not initialise cURL!');
		}

		// Add channel to URL..
		$s_url = $this->settings['url'].'/channels/'.($channel != '' ? $channel : $this->settings['channel']).'/events';

		// Build the request
		$signature = "POST\n".$s_url."\n";
		$payload_encoded = json_encode($payload);
		$query = "auth_key=".$this->settings['auth_key']."&auth_timestamp=".time()."&auth_version=1.0&body_md5=".md5($payload_encoded)."&name=".$event;

		// Socket ID set?
		if ($socket_id !== NULL )
		{
			$query .= '&socket_id='.$socket_id;
		}

		// Create the signed signature...
		$auth_signature = hash_hmac('sha256', $signature . $query, $this->settings['secret'], FALSE);
		$signed_query = $query."&auth_signature=".$auth_signature;
		$full_url = $this->settings['server'].':'.$this->settings['port'].$s_url.'?'.$signed_query;

		// Set cURL opts and execute request
		curl_setopt($ch, CURLOPT_URL, $full_url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $payload_encoded);
		curl_setopt($ch, CURLOPT_TIMEOUT, $this->settings['timeout']);

		$response = curl_exec($ch);

		curl_close($ch);

		if ($response == "202 ACCEPTED\n" && $debug == FALSE)
		{
			return TRUE;
		}
		elseif ($debug === TRUE || $this->settings['debug'] === TRUE)
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
			'sha256', $socket_id.':'.$this->settings['channel'],
			$this->settings['secret'],
			FALSE
		);
		$signature = array('auth' => $this->settings['auth_key'].':'.$signature);

		return json_encode($signature);
	}
}