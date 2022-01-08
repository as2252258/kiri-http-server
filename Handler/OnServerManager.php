<?php

namespace Server\Handler;

use Kiri\Annotation\Inject;
use Kiri\Events\EventDispatch;
use ReflectionException;
use Server\Abstracts\Server;
use Kiri\Exception\ConfigException;
use Server\Events\OnManagerStart;
use Server\Events\OnManagerStop;


/**
 * Class OnServerManager
 * @package Server\Manager
 */
class OnServerManager extends Server
{

	/**
	 * @var EventDispatch
	 */
	#[Inject(EventDispatch::class)]
	public EventDispatch $eventDispatch;


	/**
     * @param \Swoole\Server $server
     * @throws ConfigException|ReflectionException
	 */
	public function onManagerStart(\Swoole\Server $server)
	{
        $this->setProcessName(sprintf('manger[%d].0', $server->manager_pid));

		$this->eventDispatch->dispatch(new OnManagerStart($server));
	}


	/**
	 * @param \Swoole\Server $server
	 * @throws ReflectionException
	 */
	public function onManagerStop(\Swoole\Server $server)
	{
		$this->eventDispatch->dispatch(new OnManagerStop($server));
	}


}
