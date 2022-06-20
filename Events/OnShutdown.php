<?php

namespace Kiri\Server\Events;

use Swoole\Coroutine\Http\Server as CHServer;
use Swoole\Coroutine\Server as CServer;
use Swoole\Server;

class OnShutdown
{


	/**
	 * @param Server|CHServer|CServer|null $server
	 */
	public function __construct(public Server|null|CHServer|CServer $server = null)
	{
	}

}
