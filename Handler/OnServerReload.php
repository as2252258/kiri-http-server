<?php

namespace Server\Handler;

use Kiri\Annotation\Inject;
use Kiri\Events\EventDispatch;
use Server\Events\OnAfterReload;
use Server\Events\OnBeforeReload;
use Swoole\Server;


/**
 *
 */
class OnServerReload
{


	/**
	 * @var EventDispatch
	 */
	#[Inject(EventDispatch::class)]
	public EventDispatch $eventDispatch;


	/**
	 * @param Server $server
	 * @throws \ReflectionException
	 */
	public function onBeforeReload(Server $server)
	{
		$this->eventDispatch->dispatch(new OnBeforeReload($server));
	}


	/**
	 * @param Server $server
	 * @throws \ReflectionException
	 */
	public function onAfterReload(Server $server)
	{
		$this->eventDispatch->dispatch(new OnAfterReload($server));
	}

}
