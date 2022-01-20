<?php

namespace Kiri\Server\Handler;

use Kiri\Annotation\Inject;
use Kiri\Events\EventDispatch;
use Kiri\Server\Events\OnAfterReload;
use Kiri\Server\Events\OnBeforeReload;
use Swoole\Server;


/**
 *
 */
class OnServerReload
{


	/**
	 * @param Server $server
	 * @throws \ReflectionException
	 */
	public function onBeforeReload(Server $server)
	{
		\Kiri::getDi()->get(EventDispatch::class)->dispatch(new OnBeforeReload($server));
	}


	/**
	 * @param Server $server
	 * @throws \ReflectionException
	 */
	public function onAfterReload(Server $server)
	{
		\Kiri::getDi()->get(EventDispatch::class)->dispatch(new OnAfterReload($server));
	}

}
