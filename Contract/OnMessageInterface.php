<?php

namespace Server\Contract;

use Kiri\Websocket\WebSocketInterface;
use Swoole\WebSocket\Frame;

interface OnMessageInterface
{


	/**
	 * @param Frame $frame
	 * @return void
	 */
	public function onMessage(Frame $frame): void;

}
