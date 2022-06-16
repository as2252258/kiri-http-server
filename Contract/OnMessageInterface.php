<?php

namespace Kiri\Server\Contract;

use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;

interface OnMessageInterface
{


	/**
	 * @param Server|\Swoole\Coroutine\Http\Server $server
	 * @param Frame $frame
	 * @return void
	 */
	public function onMessage(Server|\Swoole\Coroutine\Http\Server $server, Frame $frame): void;

}
