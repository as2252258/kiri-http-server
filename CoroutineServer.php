<?php

namespace Kiri\Server;

use Exception;
use Kiri\Abstracts\Config;
use Kiri\Di\Inject\Container;
use Psr\Container\ContainerInterface;
use Kiri\Events\EventDispatch;
use Kiri\Exception\ConfigException;
use Kiri\Exception\NotFindClassException;
use Kiri\Server\Abstracts\ProcessManager;
use Kiri\Server\Abstracts\TraitServer;
use Kiri\Server\Config as SConfig;
use Kiri\Server\Events\OnServerBeforeStart;
use Kiri\Server\Events\OnShutdown;
use Kiri\Server\Events\OnWorkerExit;
use Kiri\Server\Events\OnWorkerStart;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use ReflectionException;
use Swoole\Coroutine;
use Swoole\Coroutine\Server as ScServer;
use Swoole\Coroutine\Http\Server as SchServer;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Process;
use Swoole\Server;

class CoroutineServer implements ServerInterface
{

	use TraitServer;


	/** @var array<SchServer|ScServer> */
	private array $servers = [];


	/**
	 * @var Server|null
	 */
	private Server|null $server = null;


	/**
	 * @param Config $config
	 * @param ContainerInterface $container
	 * @param EventDispatch $dispatch
	 * @param LoggerInterface $logger
	 * @param ProcessManager $processManager
	 */
	public function __construct(#[Container(Config::class)] public Config                         $config,
	                            #[Container(ContainerInterface::class)] public ContainerInterface $container,
	                            #[Container(EventDispatch::class)] public EventDispatch           $dispatch,
	                            #[Container(LoggerInterface::class)] public LoggerInterface       $logger,
	                            #[Container(ProcessManager::class)] public ProcessManager         $processManager)
	{
	}


	/**
	 * @param array $service
	 * @param int $daemon
	 * @return void
	 * @throws ConfigException
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 * @throws Exception
	 */
	public function initCoreServers(array $service, int $daemon = 0): void
	{
		$service = $this->genConfigService($service);
		foreach ($service as $value) {
			if ($value->getType() == Constant::SERVER_TYPE_HTTP) {
				$this->addListener($value);
			}
		}
		$rpcService = Config::get('rpc', []);
		if (!empty($rpcService)) {
			$this->addListener(instance(SConfig::class, [], $rpcService));
		}

		\Kiri::service()->set('server', $this);

		$processManager = \Kiri::getDi()->get(ProcessManager::class);
		$processManager->batch(Config::get('processes', []));
	}


	/**
	 * @param SConfig $config
	 * @throws ReflectionException
	 */
	public function addListener(SConfig $config): void
	{
		$server = new SchServer($config->getHost(), $config->getPort(), false, true);

		$events = $config->getEvents()[Constant::REQUEST] ?? null;
		if (is_null($events)) {
			$events = [\Kiri\Router\Server::class, 'onRequest'];
		}

		$events[0] = \Kiri::getDi()->get($events[0]);
		$server->handle('/', $events);

		$this->servers[] = $server;
	}


	/**
	 * @param string $name
	 * @return ScServer|SchServer|null
	 */
	public function getServer(string $name = ''): ScServer|SchServer|null
	{
		return $this->servers[$name] ?? null;
	}


	/**
	 * @return bool
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	public function shutdown(): bool
	{
		foreach ($this->servers as $server) {
			$server->shutdown();
		}

		$dispatch = \Kiri::getDi()->get(EventDispatch::class);
		$dispatch->dispatch(new OnShutdown());

		return true;
	}


	/**
	 * @param $no
	 * @param array $signInfo
	 * @return void
	 */
	public function onSigint($no, array $signInfo): void
	{
		try {
			\Kiri::getLogger()->alert('Pid ' . getmypid() . ' get signo ' . $no);
			$this->shutdown();
		} catch (\Throwable $exception) {
			error($exception);
		}
	}


	/**
	 * @param Server\Port|Server $base
	 * @param array $events
	 * @return void
	 * @throws ReflectionException
	 */
	private function onEventListen(Server\Port|Server $base, array $events): void
	{
		foreach ($events as $name => $event) {
			if (is_array($event) && is_string($event[0])) {
				$event[0] = \Kiri::getDi()->get($event[0]);
			}
			$base->on($name, $event);
		}
	}


	/**
	 * @return void
	 */
	public function start(): void
	{
		Coroutine\run(function () {
			$dispatch = \Kiri::getDi()->get(EventDispatch::class);
			$dispatch->dispatch(new OnServerBeforeStart());

			$this->onSignal(Config::get('signal', []));

			$this->onTasker();
			foreach ($this->servers as $server) {
				Coroutine::create(static function () use ($server) {

//					$this->dispatch->dispatch(new OnWorkerStart($server, 0));

					$server->start();

//					$this->dispatch->dispatch(new OnWorkerExit($server, 0));
				});
			}
		});
	}


	private Coroutine\Channel $channel;

	/**
	 * @return void
	 * @throws ReflectionException
	 */
	private function onTasker(): void
	{
		$config = Config::get('server.settings', []);

		if (isset($config[Constant::OPTION_TASK_WORKER_NUM])) {
			if ($config[Constant::OPTION_TASK_WORKER_NUM] < 1) {
				return;
			}
		}

		$taskEvents = $config['events'][Constant::TASK] ?? null;
		$finishEvents = $config['events'][Constant::FINISH] ?? null;

		if (is_null($taskEvents)) {
			return;
		}

		$container = \Kiri::getDi();
		$taskEvents[0] = $container->get($taskEvents[0]);
		if (!is_null($finishEvents)) {
			$finishEvents[0] = $container->get($finishEvents[0]);
		}

		$this->channel = new Coroutine\Channel($config[Constant::OPTION_TASK_WORKER_NUM]);
		for ($i = 0; $i < $config[Constant::OPTION_TASK_WORKER_NUM]; $i++) {
			Coroutine::create(static fn() => $this->taskRunner($i, $taskEvents, $finishEvents));
		}
	}


	/**
	 * @param $taskId
	 * @param $callback
	 * @param $finishEvents
	 * @return void
	 */
	private function taskRunner($taskId, $callback, $finishEvents): void
	{
		$taskData = $this->channel->pop();
		if (!is_null($taskData)) {
			$result = $callback($taskId, $taskData);
			if (is_callable($finishEvents, true)) {
				$finishEvents($taskId, $result);
			}
		}
		$this->taskRunner($taskId, $callback, $finishEvents);
	}

}
