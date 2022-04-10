<?php

namespace Kiri\Server\Events;

use Swoole\Server;

/**
 *
 */
class OnWorkerExit
{


    /**
     * @param Server|null $server
     * @param int $workerId
     */
	public function __construct(public ?Server $server, public int $workerId)
	{
	}


}
