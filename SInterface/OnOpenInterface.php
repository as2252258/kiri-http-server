<?php

namespace Server\SInterface;

use Swoole\Http\Request;
use Swoole\WebSocket\Server;

interface OnOpenInterface
{


    /**
     * @param \Swoole\WebSocket\Server $server
     * @param \Swoole\Http\Request $request
     */
    public function onOpen(Server $server, Request $request): void;

}
