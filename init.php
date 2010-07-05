<?php defined('SYSPATH') or die('No direct script access.');

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