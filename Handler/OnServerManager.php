<?php

namespace Kiri\Server\Handler;

use Kiri\Annotation\Inject;
use Kiri\Events\EventDispatch;
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
     * @throws ConfigException|ReflectionException
	 */
	public function onManagerStart(\Swoole\Server $server)
	{
        $this->setProcessName(sprintf('manger[%d].0', $server->manager_pid));

		\Kiri::getDi()->get(EventDispatch::class)->dispatch(new OnManagerStart($server));
	}


	/**
	 * @param \Swoole\Server $server
	 * @throws ReflectionException
	 */
	public function onManagerStop(\Swoole\Server $server)
	{
		\Kiri::getDi()->get(EventDispatch::class)->dispatch(new OnManagerStop($server));
	}


}
