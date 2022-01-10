<?php

namespace Kiri\Server\Events;

use Swoole\Server;

class OnManagerStop
{


	/**
	 * @param Server $server
	 */
	public function __construct(public Server $server)
	{
	}

}
