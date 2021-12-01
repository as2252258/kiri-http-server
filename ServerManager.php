<?php

namespace Server;

use Note\Inject;
use Exception;
use Kiri\Abstracts\Config;
use Kiri\Di\Container;
use Psr\Container\ContainerInterface;
use Kiri\Error\Logger;
use Kiri\Exception\ConfigException;
use Kiri\Kiri;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;
use Server\Abstracts\BaseProcess;
use Server\Contract\OnCloseInterface;
use Server\Contract\OnConnectInterface;
use Server\Contract\OnDisconnectInterface;
use Server\Contract\OnHandshakeInterface;
use Server\Contract\OnMessageInterface;
use Server\Contract\OnPacketInterface;
use Server\Contract\OnProcessInterface;
use Server\Contract\OnReceiveInterface;
use Server\Contract\OnTaskInterface;
use Server\Handler\OnPipeMessage;
use Server\Handler\OnServer;
use Server\Handler\OnServerManager;
use Server\Handler\OnServerReload;
use Server\Tasker\OnServerTask;
use Server\Handler\OnServerWorker;
use Swoole\Http\Server as HServer;
use Swoole\Process;
use Swoole\Server;
use Swoole\Server\Port;
use Swoole\WebSocket\Server as WServer;


/**
 * Class OnServerManager
 * @package Http\Service
 */
class ServerManager
{


	use TraitServer;

	/** @var string */
	public string $host = '';

	public int $port = 0;


	/**
	 * @var Logger
	 */
	#[Inject(Logger::class)]
	public Logger $logger;


	/**
	 * @var State
	 */
	#[Inject(State::class)]
	public State $state;



	/** @var array<string,Port> */
	public array $ports = [];

	public int $mode = SWOOLE_TCP;


	private Server|null $server = null;


	/**
	 * @var Container
	 */
	#[Inject(ContainerInterface::class)]
	public ContainerInterface $container;


	const DEFAULT_EVENT = [
		Constant::WORKER_START    => [OnServerWorker::class, 'onWorkerStart'],
		Constant::WORKER_EXIT     => [OnServerWorker::class, 'onWorkerExit'],
		Constant::WORKER_STOP     => [OnServerWorker::class, 'onWorkerStop'],
		Constant::WORKER_ERROR    => [OnServerWorker::class, 'onWorkerError'],
		Constant::MANAGER_START   => [OnServerManager::class, 'onManagerStart'],
		Constant::MANAGER_STOP    => [OnServerManager::class, 'onManagerStop'],
		Constant::BEFORE_RELOAD   => [OnServerReload::class, 'onBeforeReload'],
		Constant::AFTER_RELOAD    => [OnServerReload::class, 'onAfterReload'],
		Constant::START           => [OnServer::class, 'onStart'],
		Constant::BEFORE_SHUTDOWN => [OnServer::class, 'onBeforeShutdown'],
		Constant::SHUTDOWN        => [OnServer::class, 'onShutdown'],
	];


	private array $eventInterface = [
		OnReceiveInterface::class    => 'receive',
		OnPacketInterface::class     => 'packet',
		OnHandshakeInterface::class  => 'handshake',
		OnMessageInterface::class    => 'message',
		OnConnectInterface::class    => 'connect',
		OnCloseInterface::class      => 'close',
		OnDisconnectInterface::class => 'disconnect'
	];


	/**
	 * @return Server|WServer|HServer|null
	 */
	public function getServer(): Server|WServer|HServer|null
	{
		return $this->server;
	}


	/**
	 * @param string $type
	 * @param string $host
	 * @param int $port
	 * @param int $mode
	 * @param array $settings
	 * @throws ConfigException
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 * @throws ReflectionException
	 */
	public function addListener(string $type, string $host, int $port, int $mode, array $settings = [])
	{
		if (!$this->server) {
			$this->createBaseServer($type, $host, $port, $mode, $settings);
		} else {
			if (!isset($settings['settings'])) {
				$settings['settings'] = [];
			}
			$this->addNewListener($type, $host, $port, $mode, $settings);
		}
	}


	/**
	 * @param $configs
	 * @param int $daemon
	 * @throws ConfigException
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 * @throws ReflectionException
	 */
	public function initBaseServer($configs, int $daemon = 0): void
	{
		$context = di(ServerManager::class);
		foreach ($this->sortService($configs['ports']) as $config) {
			$this->startListenerHandler($context, $config, $daemon);
		}
		$this->bindCallback([Constant::PIPE_MESSAGE => [OnPipeMessage::class, 'onPipeMessage']]);
	}


	/**
	 * @param string|OnProcessInterface|BaseProcess $customProcess
	 * @throws Exception
	 */
	public function addProcess(string|OnProcessInterface|BaseProcess $customProcess)
	{
		if (is_string($customProcess)) {
			$customProcess = Kiri::getDi()->get($customProcess);
		}
		$system = sprintf('[%s].process', Config::get('id', 'system-service'));
		$process = new Process(function (Process $process) use ($customProcess, $system) {
			if (Kiri::getPlatform()->isLinux()) {
				$process->name($system . '(' . $customProcess->getName() . ')');
			}
			$customProcess->process($process);
		}, $customProcess->getRedirectStdinAndStdout(), $customProcess->getPipeType(), $customProcess->isEnableCoroutine());
		$this->logger->debug($system . ' ' . $customProcess->getName() . ' start.');
		$this->container->setBindings($customProcess->getName(), $process);
		$this->server->addProcess($process);
	}


	/**
	 * @return array
	 */
	public function getSetting(): array
	{
		return $this->server->setting;
	}


	/**
	 * @param string $key
	 * @param string|int $value
	 */
	public static function setEnv(string $key, string|int $value): void
	{
		putenv(sprintf('%s=%s', $key, (string)$value));
	}


	/**
	 * @param ServerManager $context
	 * @param array $config
	 * @param int $daemon
	 * @throws ConfigException
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 * @throws ReflectionException
	 * @throws Exception
	 */
	private function startListenerHandler(ServerManager $context, array $config, int $daemon = 0)
	{
		if (!$this->server) {
			$config = $this->mergeConfig($config, $daemon);
		}
		$context->addListener(
			$config['type'], $config['host'], $config['port'], $config['mode'],
			$config);
	}


	/**
	 * @param $config
	 * @param $daemon
	 * @return array
	 * @throws Exception
	 */
	private function mergeConfig($config, $daemon): array
	{
		$config['settings'] = $config['settings'] ?? [];
		$config['settings']['daemonize'] = $daemon;
		if (!isset($config['settings']['log_file'])) {
			$config['settings']['log_file'] = storage('system.log');
		}
		$config['settings']['pid_file'] = storage('.swoole.pid');
		$config['settings'][Constant::OPTION_ENABLE_REUSE_PORT] = true;
		$config['events'] = $config['events'] ?? [];
		return $config;
	}


	/**
	 * @param string $type
	 * @param string $host
	 * @param int $port
	 * @param int $mode
	 * @param array $settings
	 * @throws ConfigException
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 * @throws Exception
	 */
	private function addNewListener(string $type, string $host, int $port, int $mode, array $settings = [])
	{
		$id = Config::get('id', 'system-service');

		$this->logger->debug(sprintf('[%s].' . $type . ' service %s::%d start', $id, $host, $port));

		/** @var Server\Port $service */
		$this->ports[$port] = $this->server->addlistener($host, $port, $mode);
		if ($this->ports[$port] === false) {
			throw new Exception("The port is already in use[$host::$port]");
		}
		if ($type == Constant::SERVER_TYPE_HTTP && !isset($settings['settings']['open_http_protocol'])) {
			$settings['settings']['open_http_protocol'] = true;
			if (in_array($this->server->setting['dispatch_mode'], [2, 4])) {
				$settings['settings']['open_http2_protocol'] = true;
			}
		}
		if ($type == Constant::SERVER_TYPE_WEBSOCKET && !isset($settings['settings']['open_websocket_protocol'])) {
			$settings['settings']['open_websocket_protocol'] = true;
		}
		$this->ports[$port]->set($settings['settings'] ?? []);
		$this->addServiceEvents($settings['events'] ?? [], $this->ports[$port]);
	}


	/**
	 * @param string $type
	 * @param string $host
	 * @param int $port
	 * @param int $mode
	 * @param array $settings
	 * @throws ConfigException
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 * @throws ReflectionException
	 * @throws Exception
	 */
	private function createBaseServer(string $type, string $host, int $port, int $mode, array $settings = [])
	{
		$match = match ($type) {
			Constant::SERVER_TYPE_BASE, Constant::SERVER_TYPE_TCP,
			Constant::SERVER_TYPE_UDP => Server::class,
			Constant::SERVER_TYPE_HTTP => HServer::class,
			Constant::SERVER_TYPE_WEBSOCKET => WServer::class
		};
		$this->server = new $match($host, $port, SWOOLE_PROCESS, $mode);
		$this->server->set(array_merge(Config::get('server.settings', []), $settings['settings']));

		$id = Config::get('id', 'system-service');

		$this->logger->debug(sprintf('[%s]' . $type . ' service %s::%d start', $id, $host, $port));

		$this->addDefaultListener($settings);
	}


	/**
	 * @param array $settings
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 * @throws ReflectionException
	 */
	private function addDefaultListener(array $settings): void
	{
		if (($this->server->setting['task_worker_num'] ?? 0) > 0) {
			$this->addTaskListener($settings['events']);
		}
		$this->container->setBindings(SwooleServerInterface::class, $this->server);
		$this->addServiceEvents(ServerManager::DEFAULT_EVENT, $this->server);
		if (!empty($settings['events']) && is_array($settings['events'])) {
			$this->addServiceEvents($settings['events'], $this->server);
		}
	}


	/**
	 * @param array $events
	 * @param Server|Port $server
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 * @throws ReflectionException
	 */
	private function addServiceEvents(array $events, Server|Port $server)
	{
		foreach ($events as $name => $event) {
			if (is_array($event) && is_string($event[0])) {
				$event[0] = $this->container->get($event[0]);
			}
			$server->on($name, $event);
		}
	}


	/**
	 *
	 */
	public function start()
	{
		$this->server->start();
	}


	/**
	 * @param mixed $message
	 * @param int $workerId
	 * @return mixed
	 */
	public function sendMessage(mixed $message, int $workerId): mixed
	{
		return $this->server?->sendMessage($message, $workerId);
	}


	/**
	 * @param array $events
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 * @throws ReflectionException
	 */
	private function addTaskListener(array $events = []): void
	{
		$task_use_object = $this->server->setting['task_object'] ?? $this->server->setting['task_use_object'] ?? false;
		$reflect = $this->container->get(OnServerTask::class);
		$this->server->on('finish', $events[Constant::FINISH] ?? [$reflect, 'onFinish']);
		if ($task_use_object || $this->server->setting['task_enable_coroutine']) {
			$this->server->on('task', $events[Constant::TASK] ?? [$reflect, 'onCoroutineTask']);
		} else {
			$this->server->on('task', $events[Constant::TASK] ?? [$reflect, 'onTask']);
		}
	}


	/**
	 * @param array|null $settings
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 * @throws ReflectionException
	 */
	public function bindCallback(?array $settings = [])
	{
		if (count($settings) < 1) {
			return;
		}
		foreach ($settings as $event_type => $callback) {
			if ($this->server->getCallback($event_type) !== null) {
				continue;
			}
			if (is_array($callback) && !is_object($callback[0])) {
				$callback[0] = $this->container->get($callback[0]);
			}
			$this->server->on($event_type, $callback);
		}
	}
}
