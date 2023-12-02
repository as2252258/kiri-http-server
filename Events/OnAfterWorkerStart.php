<?php

namespace Kiri\Server\Events;

use Swoole\Server;

class OnAfterWorkerStart
{


    /**
     * @param Server $server
     * @param int $workerId
     */
    public function __construct(public Server $server, public int $workerId)
    {
    }

}
