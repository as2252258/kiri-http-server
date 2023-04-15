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

	#[Container(EventDispatch::class)]
	public EventDispatch $dispatch;



	/**
	 * @param \Swoole\Server $server
	 * @throws ConfigException
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	public function onManagerStart(\Swoole\Server $server)
	{
		Kiri::setProcessName(sprintf('manger process[%d]', $server->manager_pid));

		$this->dispatch->dispatch(new OnManagerStart($server));
	}


	/**
	 * @param \Swoole\Server $server
	 * @return void
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	public function onManagerStop(\Swoole\Server $server): void
	{
		$this->dispatch->dispatch(new OnManagerStop($server));
	}


}
