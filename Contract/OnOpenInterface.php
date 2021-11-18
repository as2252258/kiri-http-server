<?php

namespace Server\Contract;

use Swoole\Http\Request;
use Swoole\WebSocket\Server;

interface OnOpenInterface
{


    /**
     * @param Server $server
     * @param Request $request
     */
    public function onOpen(Server $server, Request $request): void;

}
