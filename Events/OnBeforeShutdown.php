<?php

namespace Server\Events;

use Swoole\Server;

class OnBeforeShutdown
{


	/**
	 * @param Server|null $server
	 */
	public function __construct(public ?Server $server = null)
	{
	}

}
