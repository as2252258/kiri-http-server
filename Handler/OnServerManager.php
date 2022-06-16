<?php

namespace Kiri\Server\Handler;

use Kiri;
use Kiri\Annotation\Inject;
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
	 * @param EventDispatch $dispatch
	 * @throws \Exception
	 */
	public function __construct(public EventDispatch $dispatch)
	{
		parent::__construct();
	}

	/**
	 * @param \Swoole\Server $server
	 * @throws ConfigException
	 * @throws ReflectionException
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	public function onManagerStart(\Swoole\Server $server)
	{
		Kiri::setProcessName(sprintf('manger[%d].0', $server->manager_pid));

		$this->dispatch->dispatch(new OnManagerStart($server));
	}


	/**
	 * @param \Swoole\Server $server
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 * @throws ReflectionException
	 */
	public function onManagerStop(\Swoole\Server $server)
	{
		$this->dispatch->dispatch(new OnManagerStop($server));
	}


}
