<?php

namespace Server\SInterface;

use Swoole\Server;
use Swoole\WebSocket\Frame;

interface OnMessageInterface
{


	/**
	 * @param Server $server
	 * @param Frame $frame
	 * @return void
	 */
	public function onMessage(Server $server, Frame $frame): void;

}
