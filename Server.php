<?php


namespace Kiri\Server;

use Exception;
use JetBrains\PhpStorm\Pure;
use Kiri;
use Kiri\Abstracts\Config;
use Kiri\Events\EventDispatch;
use Kiri\Exception\ConfigException;
use Kiri\Message\Constrict\Request;
use Kiri\Message\Constrict\RequestInterface;
use Kiri\Message\Constrict\Response;
use Kiri\Message\Constrict\ResponseInterface;
use Kiri\Message\Handler\Abstracts\HttpService;
use Kiri\Message\Handler\Router;
use Kiri\Server\Events\OnServerBeforeStart;
use Kiri\Server\Events\OnShutdown;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;
use Swoole\Coroutine;
use Kiri\Events\EventProvider;
use Kiri\Server\Events\OnWorkerStart;


defined('PID_PATH') or define('PID_PATH', APP_PATH . 'storage/server.pid');

/**
 * Class Server
 * @package Http
 */
class Server extends HttpService
{

    private array $process = [];


    private mixed $daemon = 0;


    /**
     * @param State $state
     * @param ServerManager $manager
     * @param ContainerInterface $container
     * @param ProcessManager $processManager
     * @param EventDispatch $eventDispatch
     * @param EventProvider $eventProvider
     * @param Router $router
     * @param array $config
     * @throws Exception
     */
    public function __construct(public State              $state,
                                public ServerManager      $manager,
                                public ContainerInterface $container,
                                public ProcessManager     $processManager,
                                public EventDispatch      $eventDispatch,
                                public EventProvider      $eventProvider,
                                public Router             $router,
                                array                     $config = [])
    {
        parent::__construct($config);
    }


    /**
     * @return void
     * @throws ConfigException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function init(): void
    {
        $this->container->mapping(ResponseInterface::class, Response::class);
        $this->container->mapping(RequestInterface::class, Request::class);

        $enable_coroutine = Config::get('servers.settings.enable_coroutine', false);
        if (!$enable_coroutine) {
            return;
        }
        Coroutine::set([
            'hook_flags' => SWOOLE_HOOK_ALL ^ SWOOLE_HOOK_BLOCKING_FUNCTION,
            'enable_deadlock_check' => FALSE,
            'exit_condition' => function () {
                return Coroutine::stats()['coroutine_num'] === 0;
            }
        ]);
    }


    /**
     * @param $process
     */
    public function addProcess($process)
    {
        $this->process[] = $process;
    }


    /**
     * @return void
     * @throws ConfigException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Exception
     */
    public function start(): void
    {
        $this->manager->initBaseServer(Config::get('server', [], true), $this->daemon);

        $rpcService = Config::get('rpc', []);
        if (!empty($rpcService)) {
            $this->manager->addListener($rpcService['type'], $rpcService['host'], $rpcService['port'], $rpcService['mode'], $rpcService);
        }

        $reload = Config::get('reload.hot', false);
        if ($reload !== false) {
            $this->eventProvider->on(OnWorkerStart::class, [$this, 'onWorkerStart']);

            $this->process[] = Scaner::class;
        } else {
            $this->onWorkerStart();
        }

        $processes = array_merge($this->process, Config::get('processes', []));

        $this->processManager->batch($processes);

        $this->eventDispatch->dispatch(new OnServerBeforeStart());

        $this->manager->start();
    }


    /**
     * @return void
     * @throws ReflectionException
     * @throws Exception
     */
    public function onWorkerStart(): void
    {
        scan_directory(MODEL_PATH, 'app\Model');

        $this->router->scan_build_route();
    }


    /**
     * @return void
     * @throws ConfigException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Exception
     */
    public function shutdown(): void
    {
        $configs = Config::get('server', [], true);
        foreach ($this->manager->sortService($configs['ports'] ?? []) as $config) {
            $this->state->exit($config['port']);
        }
        $this->eventDispatch->dispatch(new OnShutdown());
    }


    /**
     * @return bool
     * @throws ConfigException
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


    /**
     * @return \Swoole\Http\Server|\Swoole\Server|\Swoole\WebSocket\Server|null
     */
    #[Pure] public function getServer(): \Swoole\Http\Server|\Swoole\Server|\Swoole\WebSocket\Server|null
    {
        return $this->manager->getServer();
    }

}
