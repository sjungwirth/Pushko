<?php defined('SYSPATH') or die('No direct script access.');

class Pushko extends Pushko_Core {

	/**
	* Creates a presence signature (an extension of socket signing)
	*
	* @param int $socket_id
	* @param string $user_id
	* @param mixed $user_info
	* @return string
	*/
	public function presence_auth($socket_id, $user_id, $user_info = null )
	{

		$user_data = array( 'user_id' => $user_id );
		if($user_info)
		{
			$user_data['user_info'] = $user_info;
		}

		return $this->socket_auth($socket_id, $user_data);
	}
}
