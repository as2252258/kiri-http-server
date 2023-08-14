<?php

namespace Kiri\Server\Handler;

use Kiri\Di\Inject\Container;
use Monolog\Logger;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use ReflectionException;
use Kiri\Server\Abstracts\Server;
use Kiri\Server\Events\OnBeforeShutdown;
use Kiri\Server\Events\OnShutdown;
use Kiri\Server\Events\OnStart;
use Swoole\Server as SServer;


/**
 * Class OnServerDefault
 * @package Server\Manager
 */
class OnServer extends Server
{


    /**
     * @var Logger
     */
    #[Container(LoggerInterface::class)]
    public Logger $logger;


    /**
     * @param SServer $server
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function onStart(SServer $server): void
    {
        \Kiri::setProcessName(sprintf('start[%d].server', $server->master_pid));
        foreach (config('server.ports') as $value) {
            $this->logger->alert('Listen ' . $value['type'] . ' address ' . $value['host'] . '::' . $value['port']);
        }
        event(new OnStart($server));
    }


    /**
     * @param SServer $server
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    public function onBeforeShutdown(SServer $server): void
    {
        event(new OnBeforeShutdown($server));
    }


    /**
     * @param SServer $server
     * @throws
     */
    public function onShutdown(SServer $server): void
    {
        @unlink(storage('.swoole.pid'));
        event(new OnShutdown($server));
    }

}
