<?php

namespace Kiri\Server\Handler;

use Exception;
use Kiri\Server\Abstracts\Server;
use Kiri\Server\Contract\OnPipeMessageInterface;

/**
 *
 */
class OnPipeMessage extends Server
{



	/**
	 * @param \Swoole\Server $server
	 * @param int $src_worker_id
	 * @param mixed $message
	 * @throws Exception
	 */
	public function onPipeMessage(\Swoole\Server $server, int $src_worker_id, mixed $message)
	{
		if (is_string($message)) {
			$message = unserialize($message);
		}
		if (!is_object($message) || !($message instanceof OnPipeMessageInterface)) {
			return;
		}
		call_user_func([$message, 'process'], $server, $src_worker_id);
	}


}
