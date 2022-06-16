<?php

namespace Kiri\Server\Contract;


use Swoole\WebSocket\Server;

/**
 *
 */
interface OnCloseInterface
{


	/**
	 * @param Server|\Swoole\Coroutine\Http\Server $server
	 * @param int $fd
	 * @return void
	 */
	public function onClose(Server|\Swoole\Coroutine\Http\Server $server, int $fd): void;


}
