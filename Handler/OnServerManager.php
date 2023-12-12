<?php

namespace Kiri\Server\Handler;

use Kiri;
use ReflectionException;
use Kiri\Server\Abstracts\Server;
use Kiri\Server\Events\OnManagerStart;
use Kiri\Server\Events\OnManagerStop;


/**
 * Class OnServerManager
 * @package Server\Manager
 */
class OnServerManager extends Server
{


    /**
     * @param \Swoole\Server $server
     * @throws
     */
    public function onManagerStart(\Swoole\Server $server): void
    {
        Kiri::setProcessName(sprintf('manger process[%d]', $server->manager_pid));

        event(new OnManagerStart($server));
    }


    /**
     * @param \Swoole\Server $server
     * @return void
     * @throws
     */
    public function onManagerStop(\Swoole\Server $server): void
    {
        event(new OnManagerStop($server));
    }


}
