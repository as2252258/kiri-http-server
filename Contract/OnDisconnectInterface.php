<?php

namespace Server\Contract;

use Swoole\Server;

interface OnDisconnectInterface
{



    /**
     * @param Server $server
     * @param int $fd
     */
    public function onDisconnect(Server $server, int $fd): void;


}
