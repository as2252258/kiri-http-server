<?php

namespace Kiri\Server\Contract;

use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;

interface OnMessageInterface
{


	/**
	 * @param Frame $frame
	 * @return void
	 */
	public function OnMessage(Frame $frame): void;

}
