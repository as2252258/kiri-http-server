<?php

namespace Server\Events;

use Swoole\Server;

/**
 *
 */
class OnWorkerError
{


	/**
	 * @param Server $server
	 * @param int $worker_id
	 * @param int $worker_pid
	 * @param int $exit_code
	 * @param int $signal
	 */
	public function __construct(public Server $server, public int $worker_id, public int $worker_pid, public int $exit_code, public int $signal)
	{
	}


}
