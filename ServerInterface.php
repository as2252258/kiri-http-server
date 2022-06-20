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
	 * @return Server|\Swoole\Coroutine\Server|\Swoole\Coroutine\Http\Server|null
	 */
	public function getServer(string $name = ''): Server|\Swoole\Coroutine\Server|\Swoole\Coroutine\Http\Server|null;


	/**
	 * @param array $service
	 * @param int $daemon
	 * @return void
	 */
	public function initCoreServers(array $service, int $daemon = 0): void;


	/**
	 * @param Config $config
	 * @return void
	 */
	public function addListener(Config $config): void;


	/**
	 * @return void
	 */
	public function start(): void;

}
