<?php

namespace Kiri\Server;

use Exception;
use Kiri\Abstracts\Config;
use Kiri\Di\ContainerInterface;
use Kiri\Di\LocalService;
use Kiri\Events\EventDispatch;
use Kiri\Exception\ConfigException;
use Kiri\Exception\NotFindClassException;
use Kiri\Server\Abstracts\ProcessManager;
use Kiri\Server\Abstracts\TraitServer;
use Kiri\Server\Config as SConfig;
use Kiri\Server\Events\OnServerBeforeStart;
use Kiri\Server\Events\OnShutdown;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use ReflectionException;
use Swoole\Coroutine;
use Swoole\Coroutine\Server as ScServer;
use Swoole\Coroutine\Http\Server as SchServer;
use Swoole\Http\Request;
use Swoole\Http\Response;
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
	public function __construct(public Config             $config,
	                            public ContainerInterface $container,
	                            public EventDispatch      $dispatch,
	                            public LoggerInterface    $logger,
	                            public ProcessManager     $processManager)
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
//		$this->processManager->batch(Config::get('processes', []));
	}
	
	
	/**
	 * @param SConfig $config
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	public function addListener(SConfig $config): void
	{
		$server = new Coroutine\Http\Server($config->getHost(), $config->getPort(), false, true);
		
		$events = $config->getEvents()[Constant::REQUEST] ?? null;
		if (is_null($events)) {
			$events = [\Kiri\Message\Server::class, 'onRequest'];
		}
		
		$events[0] = $this->container->get($events[0]);
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
		
		$this->dispatch->dispatch(new OnShutdown());
		
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
			$this->logger->alert('Pid ' . getmypid() . ' get signo ' . $no);
			$this->shutdown();
		} catch (\Throwable $exception) {
			$this->logger->error($exception->getMessage());
		}
	}
	
	
	/**
	 * @param Server\Port|Server $base
	 * @param array $events
	 * @return void
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	private function onEventListen(Server\Port|Server $base, array $events): void
	{
		foreach ($events as $name => $event) {
			if (is_array($event) && is_string($event[0])) {
				$event[0] = $this->container->get($event[0]);
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
			$this->dispatch->dispatch(new OnServerBeforeStart());
			
			$this->onSignal(Config::get('signal', []));
			
			$this->onTasker();
			foreach ($this->servers as $server) {
				Coroutine::create(function () use ($server) {
					$server->start();
				});
			}
		});
	}
	
	
	private Coroutine\Channel $channel;
	
	/**
	 * @return void
	 * @throws ConfigException
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
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
		
		$taskEvents[0] = $this->container->get($taskEvents[0]);
		if (!is_null($finishEvents)) {
			$finishEvents[0] = $this->container->get($finishEvents[0]);
		}
		
		$this->channel = new Coroutine\Channel($config[Constant::OPTION_TASK_WORKER_NUM]);
		for ($i = 0; $i < $config[Constant::OPTION_TASK_WORKER_NUM]; $i++) {
			Coroutine::create(fn() => $this->taskRunner($i, $taskEvents, $finishEvents));
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
