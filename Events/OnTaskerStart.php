<?php

namespace Server\Events;

use Swoole\Server;

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
