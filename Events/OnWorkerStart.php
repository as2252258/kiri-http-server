<?php

namespace Kiri\Server\Events;

use Swoole\Server;
use Swoole\Coroutine\Server as CServer;
use Swoole\Coroutine\Http\Server as CHServer;

/**
 *
 */
class OnWorkerStart
{


	/**
	 * @param Server|CHServer|CServer|null $server
	 * @param int $workerId
	 */
	public function __construct(public Server|null|CHServer|CServer $server, public int $workerId)
	{
	}


}
