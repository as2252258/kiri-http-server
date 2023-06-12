<?php


namespace Kiri\Server;

use Exception;
use Kiri;
use Kiri\Events\EventDispatch;
use Kiri\Router\Router;
use Kiri\Server\Events\OnShutdown;
use Kiri\Server\Events\OnTaskerStart;
use Kiri\Server\Events\OnWorkerStart;
use Kiri\Server\Events\OnWorkerStop;
use Kiri\Server\Abstracts\AsyncServer;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;
use Swoole\Timer;


defined('PID_PATH') or define('PID_PATH', APP_PATH . 'storage/server.pid');


/**
 * Class Server
 * @package Http
 */
class Server
{

    /**
     * @var string|mixed
     */
    private string $class;


    /**
     * @var int
     */
    private int $daemon = 0;


    /**
     *
     */
    public function __construct()
    {
        $this->class = \config('server.type', AsyncServer::class);
    }


    /**
     * @throws ReflectionException
     */
    private function manager(): AsyncServer
    {
        return Kiri::getDi()->get($this->class);
    }


    /**
     * @param $process
     * @throws Exception
     */
    public function addProcess($process): void
    {
        $this->manager()->addProcess($process);
    }


    /**
     * @return void
     * @throws Exception
     */
    public function start(): void
    {
        on(OnWorkerStop::class, [Timer::class, 'clearAll'], 9999);
        on(OnWorkerStart::class, [$this, 'setWorkerName']);
        on(OnTaskerStart::class, [$this, 'setTaskerName']);

        $manager = Kiri::getDi()->get(Router::class);
        $manager->scan_build_route();

        $manager = $this->manager();
        $manager->initCoreServers(\config('server', [], true), $this->daemon);
        $manager->start();
    }


    /**
     * @param OnWorkerStart $onWorkerStart
     */
    public function setWorkerName(OnWorkerStart $onWorkerStart): void
    {
        if (!property_exists($onWorkerStart->server, 'worker_pid')) {
            return;
        }
        $prefix = sprintf('Worker Process[%d].%d', $onWorkerStart->server->worker_pid, $onWorkerStart->workerId);
        set_env('environmental', Kiri::WORKER);

        Kiri::setProcessName($prefix);
    }


    /**
     * @param OnTaskerStart $onWorkerStart
     */
    public function setTaskerName(OnTaskerStart $onWorkerStart): void
    {
        if (!property_exists($onWorkerStart->server, 'worker_pid')) {
            return;
        }
        $prefix = sprintf('Tasker Process[%d].%d', $onWorkerStart->server->worker_pid, $onWorkerStart->workerId);
        set_env('environmental', Kiri::TASK);

        Kiri::setProcessName($prefix);
    }


    /**
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Exception
     */
    public function shutdown(): void
    {
        $configs = \config('server', [], true);

        $state = Kiri::getDi()->get(State::class);
        foreach ($this->manager()->sortService($configs['ports'] ?? []) as $config) {
            $state->exit($config['port']);
        }

        $manager = Kiri::getDi()->get(EventDispatch::class);
        $manager->dispatch(new OnShutdown());
    }


    /**
     * @return bool
     * @throws Exception
     */
    public function isRunner(): bool
    {
        $state = Kiri::getDi()->get(State::class);
        return $state->isRunner();
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
