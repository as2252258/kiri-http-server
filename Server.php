<?php


namespace Kiri\Server;

use Exception;
use JetBrains\PhpStorm\Pure;
use Kiri;
use Kiri\Abstracts\Config;
use Kiri\Events\EventDispatch;
use Kiri\Events\EventProvider;
use Kiri\Exception\ConfigException;
use Kiri\Message\Constrict\RequestInterface;
use Kiri\Message\Constrict\ResponseInterface;
use Kiri\Message\Emitter;
use Kiri\Message\Handler\Abstracts\HttpService;
use Kiri\Message\Handler\Router;
use Kiri\Message\ResponseEmitter;
use Kiri\Server\Events\OnBeforeShutdown;
use Kiri\Server\Events\OnServerBeforeStart;
use Kiri\Server\Events\OnShutdown;
use Kiri\Server\Events\OnWorkerStart;
use Kiri\Server\Events\OnTaskerStart;
use Psr\Container\ContainerExceptionInterface;
use Kiri\Di\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Kiri\Server\Events\OnWorkerStop;
use ReflectionException;
use Kiri\Reload\Scaner;
use Swoole\WebSocket\Server as WsServer;
use Swoole\Server as SServer;
use Swoole\Http\Server as HServer;
use Swoole\Coroutine;
use Kiri\Message\Constrict\Response;
use Kiri\Message\Constrict\Request;
use Kiri\Server\Abstracts\ProcessManager;
use Kiri\Server\Abstracts\AsyncServer;


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
	 * @param AsyncServer $manager
	 * @param ContainerInterface $container
	 * @param ProcessManager $processManager
	 * @param EventDispatch $dispatch
	 * @param EventProvider $provider
	 * @param Router $router
	 * @param array $config
	 * @throws Exception
	 */
	public function __construct(public State              $state,
	                            public AsyncServer        $manager,
	                            public ContainerInterface $container,
	                            public ProcessManager     $processManager,
	                            public EventDispatch      $dispatch,
	                            public EventProvider      $provider,
	                            public Router             $router,
	                            array                     $config = [])
	{
		parent::__construct($config);
	}


	/**
	 * @return void
	 * @throws ConfigException
	 */
	public function init(): void
	{
		$enable_coroutine = Config::get('servers.settings.enable_coroutine', false);
		if (!$enable_coroutine) {
			return;
		}
		Coroutine::set([
			'hook_flags'            => SWOOLE_HOOK_ALL ^ SWOOLE_HOOK_BLOCKING_FUNCTION,
			'enable_deadlock_check' => FALSE,
			'exit_condition'        => function () {
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
		$this->container->mapping(Emitter::class, ResponseEmitter::class);
		$this->container->mapping(ResponseInterface::class, Response::class);
		$this->container->mapping(RequestInterface::class, Request::class);

		$this->manager->initCoreServers(Config::get('server', [], true), $this->daemon);

		$rpcService = Config::get('rpc', []);
		if (!empty($rpcService)) {
			/** @var \Kiri\Server\Config $create */
			$create = $this->container->create(\Kiri\Server\Config::class, [], $rpcService);
			$this->manager->addListener($create);
		}

		pcntl_signal(SIGINT, [$this, 'onSigint']);

		$this->onHotReload();

		$this->processManager->batch($this->process, $this->manager->getServer());
		$this->dispatch->dispatch(new OnServerBeforeStart());
		$this->manager->start();
	}


	/**
	 * @return void
	 * @throws Exception
	 */
	protected function onWorkerListener(): void
	{
		$this->provider->on(OnWorkerStop::class, '\Swoole\Timer::clearAll');
		$this->provider->on(OnWorkerStart::class, [$this, 'setWorkerName']);
		$this->provider->on(OnTaskerStart::class, [$this, 'setTaskerName']);
	}


	/**
	 * @param OnWorkerStart $onWorkerStart
	 * @throws ConfigException
	 */
	public function setWorkerName(OnWorkerStart $onWorkerStart): void
	{
		$prefix = sprintf('Worker Process[%d].%d', $onWorkerStart->server->worker_pid, $onWorkerStart->workerId);
		set_env('environmental', Kiri::WORKER);

		Kiri::setProcessName($prefix);
	}


	/**
	 * @param OnTaskerStart $onWorkerStart
	 * @throws ConfigException
	 */
	public function setTaskerName(OnTaskerStart $onWorkerStart): void
	{
		$prefix = sprintf('Tasker Process[%d].%d', $onWorkerStart->server->worker_pid, $onWorkerStart->workerId);
		set_env('environmental', Kiri::TASK);

		Kiri::setProcessName($prefix);
	}


	/**
	 * @return void
	 * @throws ConfigException
	 * @throws ReflectionException
	 * @throws Exception
	 */
	public function onHotReload(): void
	{
		$this->onWorkerListener();
		$reload = Config::get('reload.hot', false);
		if ($reload !== false) {
			$this->provider->on(OnWorkerStart::class, [$this, 'LoadRoutingList']);

			$this->process[] = Scaner::class;
		} else {
			$this->LoadRoutingList();
		}
	}


	/**
	 * @return void
	 */
	public function onSigint(): void
	{
		try {
			$this->dispatch->dispatch(new OnBeforeShutdown());
		} catch (\Throwable $exception) {
			$this->logger->error($exception->getMessage());
		} finally {
			$this->manager->getServer()->shutdown();
		}
	}


	/**
	 * @return void
	 * @throws ReflectionException
	 * @throws Exception
	 */
	public function LoadRoutingList(): void
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
		$this->dispatch->dispatch(new OnShutdown());
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
	 * @return HServer|SServer|WsServer|null
	 */
	#[Pure] public function getServer(): HServer|SServer|WsServer|null
	{
		return $this->manager->getServer();
	}

}
