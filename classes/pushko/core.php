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
	 * Create and return new Pusko object
	 *
	 * @param string $name
	 * @return Pushko
	 */
	public static function factory($name = NULL)
	{
		return new Pushko($name);
	}

	/**
	 * @var	array	Configuration array
	 */
	protected $_config;

	/**
	 * Init Pushko
	 *
	 * @param string $name
	 */
	public function __construct($name = NULL)
	{
		if ($name === NULL)
		{
			$name = Pushko::$default_config_name;
		}

		$this->_config = Kohana::$config->load('pusher')->$name;

		if (empty($this->_config['app_id']))
		{
			throw new Pushko_Exception('Pusher app_id is not defined in :name configuration',
					array(':name' => $name));
		}

		if (empty($this->_config['auth_key']))
		{
			throw new Pushko_Exception('Pusher auth_key is not defined in :name configuration',
					array(':name' => $name));
		}

		if (empty($this->_config['secret']))
		{
			throw new Pushko_Exception('Pusher secret is not defined in :name configuration',
					array(':name' => $name));
		}

		$this->_config['api'] = '/apps/'.$this->_config['app_id'];
	}

	/**
	 * Validate number of channels and channel name format.
	 *
	 * @param array $channels
	 * @throws Pushko_Exception
	 */
	protected function validate_channels(array $channels)
	{
		if (count($channels) > 100)
		{
			throw new Pushko_Exception('An event can be triggered on a maximum of 100 channels in a single call.');
		}

		array_walk($channels, array($this, 'validate_channel' ) );
	}

	/**
	 * Ensure a channel name is valid based on our spec
	 *
	 * @param $channel
	 * @throws Pushko_Exception
	 */
	protected function validate_channel($channel)
	{
		if ( ! preg_match('/\A[-a-zA-Z0-9_=@,.;]+\z/', $channel))
		{
			throw new Pushko_Exception('Invalid channel name '.$channel);
		}
	}

	/**
	 * Ensure a socket_id is valid based on our spec
	 *
	 * @param $socket_id
	 * @throws Pushko_Exception
	 */
	protected function validate_socket_id($socket_id)
	{
		if ($socket_id !== NULL && ! preg_match('/\A\d+\.\d+\z/', $socket_id))
		{
			throw new Pushko_Exception('Invalid socket ID '.$socket_id);
		}
	}

	/**
	 * Trigger an event by providing event name and payload.
	 * Optionally provide a socket ID to exclude a client (most likely the sender).
	 * @throws Pushko_Exception
	 *
	 * @param	mixed	$channels - string or array of channel names
	 * @param	string	$event
	 * @param	mixed	$data
	 * @param	integer	$socket_id [optional]
	 * @param	boolean	$debug [optional]
	 * @return	mixed
	 */
	public function trigger($channels, $event, $data, $socket_id = NULL, $debug = FALSE)
	{
		$this->validate_channels((array) $channels);
		$this->validate_socket_id($socket_id);

		$path = $this->_config['api'].'/events';

		// Build the request
		$request_params = array(
			'data' => json_encode($data),
			'name' => $event,
			'channels' => array_values((array) $channels),
		);

		// socketid
		if ($socket_id !== NULL)
		{
			$request_params['socket_id'] = $socket_id;
		}

		$query_params = array();
		$payload_encoded = json_encode($request_params);
		$query_params['body_md5'] = md5($payload_encoded);

		// Create the signed query...
		$signed_query = $this->auth_signature('POST', $path, $query_params);

		$url = $this->_config['server'].':'.$this->_config['port'].$path.'?'.$signed_query;

		// Check if we can initialize a cURL connection
		$ch = curl_init();
		if ($ch === FALSE)
		{
			throw new Pushko_Exception('Could not initialise cURL!');
		}
		// Set cURL opts and execute request
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $payload_encoded);
		curl_setopt($ch, CURLOPT_TIMEOUT, $this->_config['timeout']);

		$response = curl_exec($ch);

		curl_close($ch);

		if ($response == "{}" && $debug == FALSE)
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
	 * @return bool|stdClass
	 * @throws Pushko_Exception
	 */
	public function get_channels()
	{
		// Check if we can initialize a cURL connection
		$ch = curl_init();
		if ($ch === FALSE)
		{
			throw new Pushko_Exception('Could not initialise cURL!');
		}

		$path = $this->_config['api'].'/channels';

		// Create the signed signature...
		$signed_query = $this->auth_signature('GET', $path);

		$url = $this->_config['server'].':'.$this->_config['port'].$path.'?'.$signed_query;

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT, $this->_config['timeout']);

		$response = curl_exec($ch);

		$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		if ($http_status == 200)
		{
			$response = json_decode($response);
			$response = $response->channels;
		}
		else
		{
			$response = false;
		}

		curl_close($ch);

		return $response;
	}

	/**
	 * @param $channel
	 * @return bool|stdClass
	 * @throws Pushko_Exception
	 */
	public function get_channel_stats($channel)
	{
		// Check if we can initialize a cURL connection
		$ch = curl_init();
		if ($ch === FALSE)
		{
			throw new Pushko_Exception('Could not initialise cURL!');
		}

		$path = $this->_config['api'].'/channels/'.$channel.'/stats';

		// Create the signed signature...
		$signed_query = $this->auth_signature('GET', $path);

		$url = $this->_config['server'].':'.$this->_config['port'].$path.'?'.$signed_query;

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT, $this->_config['timeout']);

		$response = curl_exec($ch);

		$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		if ($http_status == 200)
		{
			$response = json_decode($response);
		}
		else
		{
			$response = false;
		}

		curl_close($ch);

		return $response;
	}

	/**
	 * Creates a socket signature
	 *
	 * @param    string  $channel
	 * @param    integer $socket_id
	 * @param    mixed   $data
	 * @return   array
	 */
	public function socket_auth($channel, $socket_id, $data = null)
	{
		if ($data)
		{
			// encode the data
			$data = (is_string($data) ? $data : json_encode($data));
		}

		$this->validate_channel($channel);
		$this->validate_socket_id($socket_id);

		$auth = array(
			'auth' => $this->_config['auth_key'].':'.hash_hmac(
				'sha256', $socket_id.':'.$channel.($data?':'.$data:null),
				$this->_config['secret'],
				FALSE
			),
		);

		// add the custom data if it has been supplied
		if ($data)
		{
			$auth['channel_data'] = $data;
		}

		return $auth;
	}

	/**
	* Creates a presence signature (an extension of socket signing)
	*
	* @param int $channel
	* @param int $socket_id
	* @param string $user_id
	* @param mixed $user_info
	* @return array
	*/
	public function presence_auth($channel, $socket_id, $user_id, $user_info = null )
	{

		$user_data = array('user_id' => $user_id);
		if($user_info)
		{
			$user_data['user_info'] = $user_info;
		}

		return $this->socket_auth($channel, $socket_id, $user_data);
	}

	/**
	 * @param $request_method
	 * @param $request_path
	 * @param array $query_params
	 * @param string $auth_version
	 * @param null $auth_timestamp
	 * @return string
	 */
	public function auth_signature($request_method, $request_path,
	  $query_params = array(), $auth_version = '1.0', $auth_timestamp = null)
	{
		$auth_key = $this->_config['auth_key'];
		$auth_secret = $this->_config['secret'];
		$params = array();
		$params['auth_key'] = $auth_key;
		$params['auth_timestamp'] = (is_null($auth_timestamp)?time() : $auth_timestamp);
		$params['auth_version'] = $auth_version;

		$params = array_merge($params, $query_params);
		ksort($params);

		$string_to_sign = "$request_method\n".$request_path."\n".implode('&', Arr::kv_pair($params));

		$auth_signature = hash_hmac( 'sha256', $string_to_sign, $auth_secret, false );

		$params['auth_signature'] = $auth_signature;
		ksort($params);

		$auth_query_string = implode('&', Arr::kv_pair($params));

		return $auth_query_string;
	}
}
