<?php

namespace Kiri\Server\Events;

use Kiri\Exception\ConfigException;
use Swoole\Server;
use Kiri;

/**
 *
 */
class OnTaskerStart
{


	/**
	 * @param Server $server
	 * @param int $workerId
	 */
	public function __construct(public Server $server, public int $workerId)
	{
	}


}
