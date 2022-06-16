<?php

namespace Kiri\Server\Handler;

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
	 * @param EventDispatch $dispatch
	 * @throws \Exception
	 */
	public function __construct(public EventDispatch $dispatch)
	{
		parent::__construct();
	}


	/**
	 * @param SServer $server
	 * @throws ConfigException
	 * @throws ReflectionException
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	public function onStart(SServer $server)
	{
		\Kiri::setProcessName(sprintf('start[%d].server', $server->master_pid));

		$this->dispatch->dispatch(new OnStart($server));
	}


	/**
	 * @param SServer $server
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 * @throws ReflectionException
	 */
	public function onBeforeShutdown(SServer $server)
	{
		$this->dispatch->dispatch(new OnBeforeShutdown($server));
	}


	/**
	 * @param SServer $server
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 * @throws ReflectionException
	 */
	public function onShutdown(SServer $server)
	{
		$this->dispatch->dispatch(new OnShutdown($server));
	}


	/**
	 * @param SServer $server
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 * @throws ReflectionException
	 */
	public function onBeforeReload(SServer $server)
	{
		$this->dispatch->dispatch(new OnBeforeReload($server));
	}


	/**
	 * @param SServer $server
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 * @throws ReflectionException
	 */
	public function onAfterReload(SServer $server)
	{
		$this->dispatch->dispatch(new OnAfterReload($server));
	}



}
