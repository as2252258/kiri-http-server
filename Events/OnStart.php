<?php

namespace Server\Events;

use Swoole\Server;

class OnStart
{


	/**
	 * @param Server $server
	 */
	public function __construct(public Server $server)
	{
	}

}
