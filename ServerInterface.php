<?php

namespace Kiri\Server;


use Swoole\Server;

/**
 * @mixin Server
 */
interface ServerInterface
{


	/**
	 * @param string $name
	 * @return Server|\Swoole\Coroutine\Server|\Swoole\Coroutine\Http\Server
	 */
	public function getServer(string $name): Server|\Swoole\Coroutine\Server|\Swoole\Coroutine\Http\Server;

}
