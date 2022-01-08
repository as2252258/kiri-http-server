<?php

namespace Server\Events;

use Swoole\Server;

/**
 *
 */
class OnWorkerStop
{


	/**
	 * @param Server $server
	 * @param int $workerId
	 */
	public function __construct(public Server $server, public int $workerId)
	{
	}


}
