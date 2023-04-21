<?php

namespace Kiri\Server\Handler;

use Kiri\Di\Inject\Container;
use Kiri\Events\EventDispatch;
use Kiri\Exception\ConfigException;
use Kiri\Server\Events\OnAfterReload;
use Kiri\Server\Events\OnBeforeReload;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;
use Kiri\Server\Abstracts\Server;
use Kiri\Server\Events\OnBeforeShutdown;
use Kiri\Server\Events\OnShutdown;
use Kiri\Server\Events\OnStart;
use Swoole\Server as SServer;


/**
 * Class OnServerDefault
 * @package Server\Manager
 */
class OnServer extends Server
{


	/**
	 * @param SServer $server
	 * @throws ConfigException
	 * @throws ReflectionException
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	public function onStart(SServer $server): void
	{
		\Kiri::setProcessName(sprintf('start[%d].server', $server->master_pid));

		event(new OnStart($server));
	}


	/**
	 * @param SServer $server
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 * @throws ReflectionException
	 */
	public function onBeforeShutdown(SServer $server): void
	{
		event(new OnBeforeShutdown($server));
	}


	/**
	 * @param SServer $server
	 * @throws
	 */
	public function onShutdown(SServer $server): void
	{
		@unlink(storage('.swoole.pid'));
		event(new OnShutdown($server));
	}

}
