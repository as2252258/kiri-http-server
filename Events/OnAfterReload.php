<?php

namespace Server\Events;

use Swoole\Server;

class OnAfterReload
{


	/**
	 * @param Server $server
	 */
	public function __construct(public Server $server)
	{
	}

}
