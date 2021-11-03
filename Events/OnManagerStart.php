<?php

namespace Server\Events;

use Swoole\Server;

class OnManagerStart
{

	/**
	 * @param Server $server
	 */
	public function __construct(public Server $server)
	{
	}
}
