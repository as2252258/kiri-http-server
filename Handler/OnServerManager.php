<?php

namespace Kiri\Server\Handler;

use Kiri;
use Kiri\Di\Inject\Container;
use Kiri\Events\EventDispatch;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;
use Kiri\Server\Abstracts\Server;
use Kiri\Exception\ConfigException;
use Kiri\Server\Events\OnManagerStart;
use Kiri\Server\Events\OnManagerStop;


/**
 * Class OnServerManager
 * @package Server\Manager
 */
class OnServerManager extends Server
{


	/**
	 * @param \Swoole\Server $server
	 * @throws ConfigException
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface|ReflectionException
	 */
	public function onManagerStart(\Swoole\Server $server)
	{
		Kiri::setProcessName(sprintf('manger process[%d]', $server->manager_pid));

		$dispatch = \Kiri::getDi()->get(EventDispatch::class);
		$dispatch->dispatch(new OnManagerStart($server));
	}


	/**
	 * @param \Swoole\Server $server
	 * @return void
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface|ReflectionException
	 */
	public function onManagerStop(\Swoole\Server $server): void
	{
		$dispatch = \Kiri::getDi()->get(EventDispatch::class);
		$dispatch->dispatch(new OnManagerStop($server));
	}


}
