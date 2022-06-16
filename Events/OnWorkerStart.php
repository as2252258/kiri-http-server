<?php

namespace Kiri\Server\Events;

use Swoole\Server;

/**
 *
 */
class OnWorkerStart
{


	/**
	 * @param Server|null $server
	 * @param int $workerId
	 */
	public function __construct(public ?Server $server, public int $workerId)
	{
	}


}
