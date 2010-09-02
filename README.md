Pushko
==================

This is a very simple Kohana 3 module for the Pusher REST API (http://www.pusherapp.com)

Using it is easy as pie:

	$pusher = Pushko::factory('test_channel');
	$pusher->trigger('test_event', 'Hello, pushko!');

Channels
---------
You can specify the channel either while constructing the Pusher object as described above, or while triggering an event:

    $pusher->trigger('event', 'data', null, 'channel');

Socket id
---------
In order to avoid duplicates you can optionally specify the sender's socket id while triggering an event (http://pusherapp.com/docs/duplicates):

    $pusher->trigger('event','data','socket_id');

License
-------
Copyright 2010, 2bj. Licensed under the MIT license: http://www.opensource.org/licenses/mit-license.php

Copyright 2010, Squeeks. Licensed under the MIT license: http://www.opensource.org/licenses/mit-license.php