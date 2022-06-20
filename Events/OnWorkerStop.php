<?php

namespace Kiri\Server\Events;

use Swoole\Coroutine\Http\Server as CHServer;
use Swoole\Coroutine\Server as CServer;
use Swoole\Server;

/**
 *
 */
class OnWorkerStop
{


	/**
	 * @param Server|CHServer|CServer|null $server
	 * @param int $workerId
	 */
	public function __construct(public Server|null|CHServer|CServer $server, public int $workerId)
	{
	}


}
