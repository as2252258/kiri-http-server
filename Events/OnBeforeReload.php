<?php

namespace Server\Events;

use Swoole\Server;

class OnBeforeReload
{


	/**
	 * @param Server $server
	 */
	public function __construct(public Server $server)
	{
	}

}
