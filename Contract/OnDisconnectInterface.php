<?php

namespace Kiri\Server\Contract;

use Swoole\WebSocket\Server;

interface OnDisconnectInterface
{


	/**
	 * @param Server $server
	 * @param int $fd
	 * @return void
	 */
	public function onDisconnect(Server $server, int $fd): void;

}
