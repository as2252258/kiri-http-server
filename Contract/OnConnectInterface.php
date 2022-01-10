<?php

namespace Kiri\Server\Contract;

use Swoole\Server;

interface OnConnectInterface
{


	/**
	 * @param Server $server
	 * @param int $fd
	 * @return void
	 */
	public function onConnect(Server $server, int $fd): void;

}
