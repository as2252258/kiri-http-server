<?php

namespace Kiri\Server\Contract;

use Swoole\Server;

interface OnDisconnectInterface
{



    /**
     * @param int $fd
     */
    public function onDisconnect(int $fd): void;


}
