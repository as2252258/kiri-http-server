<?php

namespace Server\Handler;

use Note\Inject;
use Kiri\Events\EventDispatch;
use Kiri\Exception\ConfigException;
use Server\Abstracts\Server;
use Server\Events\OnBeforeShutdown;
use Server\Events\OnShutdown;
use Server\Events\OnStart;


/**
 * Class OnServerDefault
 * @package Server\Manager
 */
class OnServer extends Server
{

	/**
	 * @var EventDispatch
	 */
	#[Inject(EventDispatch::class)]
	public EventDispatch $eventDispatch;


	/**
	 * @param \Swoole\Server $server
	 * @throws ConfigException
	 */
	public function onStart(\Swoole\Server $server)
	{
		$this->setProcessName(sprintf('start[%d].server', $server->master_pid));

		$this->eventDispatch->dispatch(new OnStart($server));
	}


	/**
	 * @param \Swoole\Server $server
	 */
	public function onBeforeShutdown(\Swoole\Server $server)
	{
		$this->eventDispatch->dispatch(new OnBeforeShutdown($server));
	}


	/**
	 * @param \Swoole\Server $server
	 */
	public function onShutdown(\Swoole\Server $server)
	{
		$this->eventDispatch->dispatch(new OnShutdown($server));
	}


}
