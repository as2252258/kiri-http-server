<?php

namespace Server\Handler;

use Note\Inject;
use Server\Abstracts\Server;
use Exception;
use Server\Contract\OnPipeMessageInterface;
use Kiri\Events\EventDispatch;

/**
 *
 */
class OnPipeMessage extends Server
{


	/** @var EventDispatch  */
	#[Inject(EventDispatch::class)]
	public EventDispatch $eventDispatch;


	/**
	 * @param \Swoole\Server $server
	 * @param int $src_worker_id
	 * @param mixed $message
	 * @throws Exception
	 */
	public function onPipeMessage(\Swoole\Server $server, int $src_worker_id, mixed $message)
	{
		if (!is_object($message) || !($message instanceof OnPipeMessageInterface)) {
			return;
		}
		call_user_func([$message, 'process'], $server, $src_worker_id);
	}


}
