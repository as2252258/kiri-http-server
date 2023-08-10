<?php


namespace Kiri\Server;

use Exception;
use Kiri;
use Kiri\Events\EventDispatch;
use Kiri\Router\Router;
use Kiri\Server\Events\OnShutdown;
use Kiri\Server\Abstracts\AsyncServer;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;


defined('PID_PATH') or define('PID_PATH', APP_PATH . 'storage/server.pid');


/**
 * Class Server
 * @package Http
 */
class Server
{


    /**
     * @var int
     */
    private int $daemon = 0;


    /**
     * @param AsyncServer $manager
     * @param State $state
     * @param EventDispatch $dispatch
     * @param Router $router
     */
    public function __construct(public AsyncServer   $manager,
                                public State         $state,
                                public EventDispatch $dispatch,
                                public Router        $router)
    {
    }


    /**
     * @return void
     * @throws Exception
     */
    public function start(): void
    {
        if (\config('reload.hot', false) === true) {
            $this->manager->addProcess(HotReload::class);
        } else {
            $this->router->scan_build_route();
        }
        $this->manager->initCoreServers(\config('server', []), $this->daemon);
        $this->manager->start();
    }


    /**
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Exception
     */
    public function shutdown(): void
    {
        $configs = \config('server', []);
        $instances = $this->manager->sortService($configs['ports'] ?? []);
        foreach ($instances as $config) {
            $this->state->exit($config->port);
        }
        $this->dispatch->dispatch(new OnShutdown());
    }


    /**
     * @return bool
     * @throws Exception
     */
    public function isRunner(): bool
    {
        return $this->state->isRunner();
    }


    /**
     * @param $daemon
     * @return Server
     */
    public function setDaemon($daemon): static
    {
        if (!in_array($daemon, [0, 1])) {
            return $this;
        }
        $this->daemon = $daemon;
        return $this;
    }
}
