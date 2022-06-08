<?php


namespace Kiri\Server;

use Exception;
use JetBrains\PhpStorm\Pure;
use Kiri;
use Kiri\Abstracts\Config;
use Kiri\Events\EventDispatch;
use Kiri\Events\EventProvider;
use Kiri\Exception\ConfigException;
use Kiri\Message\Constrict\Request;
use Kiri\Message\Constrict\RequestInterface;
use Kiri\Message\Constrict\Response;
use Kiri\Message\Constrict\ResponseInterface;
use Kiri\Message\Handler\Abstracts\HttpService;
use Kiri\Message\Handler\Router;
use Kiri\Server\Events\OnBeforeShutdown;
use Kiri\Server\Events\OnServerBeforeStart;
use Kiri\Server\Events\OnShutdown;
use Kiri\Server\Events\OnWorkerStart;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;
use Swoole\WebSocket\Server as WsServer;
use Swoole\Server as SServer;
use Swoole\Http\Server as HServer;
use Swoole\Coroutine;


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
	 * @param EventDispatch $dispatch
	 * @param EventProvider $provider
	 * @param Router $router
	 * @param array $config
	 * @throws Exception
	 */
	public function __construct(public State              $state,
	                            public ServerManager      $manager,
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
		$this->manager->initBaseServer(Config::get('server', [], true), $this->daemon);

		$rpcService = Config::get('rpc', []);
		if (!empty($rpcService)) {
			$this->manager->addListener($rpcService['type'], $rpcService['host'], $rpcService['port'], $rpcService['mode'], $rpcService);
		}

		$this->onHotReload();

		pcntl_signal(SIGINT, [$this, 'onSigint']);
		$processes = array_merge($this->process, Config::get('processes', []));
		$this->processManager->batch($processes);
		$this->dispatch->dispatch(new OnServerBeforeStart());
		$this->manager->start();
	}


	/**
	 * @return void
	 * @throws ConfigException
	 * @throws ReflectionException
	 */
	public function onHotReload(): void
	{
		$reload = Config::get('reload.hot', false);
		if ($reload !== false) {
			$this->provider->on(OnWorkerStart::class, [$this, 'onWorkerStart']);

			$this->process[] = Scaner::class;
		} else {
			$this->onWorkerStart();
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
