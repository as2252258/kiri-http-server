<?php

namespace Kiri\Server\Contract;

use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;

interface OnMessageInterface
{


	/**
	 * @param Server $server
	 * @param Frame $frame
	 * @return void
	 */
	public function onMessage(Server $server, Frame $frame): void;

}
