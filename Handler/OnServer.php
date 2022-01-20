<?php

namespace Kiri\Server\Handler;

use Kiri\Annotation\Inject;
use Kiri\Events\EventDispatch;
use Kiri\Exception\ConfigException;
use ReflectionException;
use Kiri\Server\Abstracts\Server;
use Kiri\Server\Events\OnBeforeShutdown;
use Kiri\Server\Events\OnShutdown;
use Kiri\Server\Events\OnStart;


/**
 * Class OnServerDefault
 * @package Server\Manager
 */
class OnServer extends Server
{
	

	/**
	 * @param \Swoole\Server $server
	 * @throws ConfigException
	 * @throws ReflectionException
	 */
	public function onStart(\Swoole\Server $server)
	{
		$this->setProcessName(sprintf('start[%d].server', $server->master_pid));

		\Kiri::getDi()->get(EventDispatch::class)->dispatch(new OnStart($server));
	}


	/**
	 * @param \Swoole\Server $server
	 * @throws ReflectionException
	 */
	public function onBeforeShutdown(\Swoole\Server $server)
	{
		\Kiri::getDi()->get(EventDispatch::class)->dispatch(new OnBeforeShutdown($server));
	}


	/**
	 * @param \Swoole\Server $server
	 * @throws ReflectionException
	 */
	public function onShutdown(\Swoole\Server $server)
	{
		\Kiri::getDi()->get(EventDispatch::class)->dispatch(new OnShutdown($server));
	}


}
