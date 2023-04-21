<?php

namespace Kiri\Server\Contract;

use Swoole\Http\Request;
use Swoole\Http\Server;

interface OnOpenInterface
{


	/**
	 * @param Server $server
	 * @param Request $request
	 * @return void
	 */
	public function onOpen(Server $server, Request $request): void;

}
