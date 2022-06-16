<?php

namespace Kiri\Server\Contract;

use Swoole\Http\Request;
use Swoole\WebSocket\Server;
use Swoole\Coroutine\Http\Server as HServer;

interface OnOpenInterface
{


	/**
	 * @param Server|HServer $server
	 * @param Request $request
	 * @return void
	 */
    public function onOpen(Server|HServer $server, Request $request): void;

}
