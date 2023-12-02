<?php

namespace Kiri\Server\Events;

use Swoole\Server;

class OnBeforeWorkerStart
{

    /**
     * @param Server $server
     * @param int $workerId
     */
    public function __construct(public Server $server,public int $workerId)
    {
    }

}
