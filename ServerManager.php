<?php

namespace Server;

use Annotation\Inject;
use Closure;
use Exception;
use Kiri\Abstracts\Config;
use Kiri\Di\ContainerInterface;
use Kiri\Exception\ConfigException;
use Kiri\Kiri;
use ReflectionException;
use Server\Abstracts\BaseProcess;
use Server\Handler\OnPipeMessage;
use Server\Handler\OnServer;
use Server\Handler\OnServerManager;
use Server\Handler\OnServerReload;
use Server\Handler\OnServerTask;
use Server\Handler\OnServerWorker;
use Server\SInterface\OnCloseInterface;
use Server\SInterface\OnConnectInterface;
use Server\SInterface\OnDisconnectInterface;
use Server\SInterface\OnHandshakeInterface;
use Server\SInterface\OnMessageInterface;
use Server\SInterface\OnPacketInterface;
use Server\SInterface\OnProcessInterface;
use Server\SInterface\OnReceiveInterface;
use Server\SInterface\OnTaskInterface;
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

	/** @var string */
	public string $host = '';

	public int $port = 0;


	/** @var array<string,Port> */
	public array $ports = [];

	public int $mode = SWOOLE_TCP;


	private Server|null $server = null;


	/**
	 * @var ContainerInterface
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
	 * @throws ReflectionException
	 * @throws ConfigException
	 * @throws Exception
	 */
	public function addListener(string $type, string $host, int $port, int $mode, array $settings = [])
	{
		if ($this->checkPortIsAlready($port)) $this->stopServer($port);
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
	 * @throws ReflectionException
	 * @throws ConfigException
	 */
	public function initBaseServer($configs, int $daemon = 0): void
	{
		$context = di(ServerManager::class);
		foreach ($this->sortService($configs['ports']) as $config) {
			$this->startListenerHandler($context, $config, $daemon);
		}
		$this->bindCallback($this->server, [Constant::PIPE_MESSAGE => [OnPipeMessage::class, 'onPipeMessage']]);
//		$this->bindCallback($this->server, $this->getSystemEvents($configs));
	}


	/**
	 * @return bool
	 * @throws ConfigException
	 * @throws Exception
	 */
	public function isRunner(): bool
	{
		$configs = Config::get('server', [], true);
		foreach ($this->sortService($configs['ports']) as $config) {
			if ($this->checkPortIsAlready($config['port'])) {
				return true;
			}
		}
		return false;
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
		$process = new Process(function (Process $soloProcess) use ($customProcess) {
			$system = sprintf('[%s].process[%d]', Config::get('id', 'system-service'), $soloProcess->pid);
			if (Kiri::getPlatform()->isLinux()) {
				$soloProcess->name($system . '.' . $customProcess->getName() . ' start.');
			}
			echo "\033[36m[" . date('Y-m-d H:i:s') . "]\033[0m " . $system . $customProcess->getName() . ' start.' . PHP_EOL;
			$customProcess->signListen($soloProcess);
			$customProcess->onHandler($soloProcess);
		},
			$customProcess->getRedirectStdinAndStdout(),
			$customProcess->getPipeType(),
			$customProcess->isEnableCoroutine()
		);
		$this->container->setBindings($customProcess::class, $process);
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
	 * @param array $ports
	 * @return array
	 */
	public function sortService(array $ports): array
	{
		$array = [];
		foreach ($ports as $port) {
			if ($port['type'] == Constant::SERVER_TYPE_WEBSOCKET) {
				array_unshift($array, $port);
			} else if ($port['type'] == Constant::SERVER_TYPE_HTTP) {
				if (!empty($array) && $array[0]['type'] == Constant::SERVER_TYPE_WEBSOCKET) {
					$array[] = $port;
				} else {
					array_unshift($array, $port);
				}
			} else {
				$array[] = $port;
			}
		}
		return $array;
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
		if (!isset($config['settings']['daemonize']) || !$config['settings']['daemonize'] != $daemon) {
			$config['settings']['daemonize'] = $daemon;
		}
		if (!isset($config['settings']['log_file'])) {
			$config['settings']['log_file'] = storage('system.log');
		}
		$config['settings']['pid_file'] = storage('.swoole.pid');
		$config['events'] = $config['events'] ?? [];
		return $config;
	}


	/**
	 * @param string $type
	 * @param string $host
	 * @param int $port
	 * @param int $mode
	 * @param array $settings
	 * @throws Exception
	 */
	private function addNewListener(string $type, string $host, int $port, int $mode, array $settings = [])
	{
		$id = Config::get('id', 'system-service');

		echo sprintf("\033[36m[" . date('Y-m-d H:i:s') . "]\033[0m [%s]$type service %s::%d start.", $id, $host, $port) . PHP_EOL;
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
	 * @param int $port
	 * @param string $event
	 * @return Closure|array|null
	 */
	public function getPortCallback(int $port, string $event): Closure|array|null
	{
		/** @var Server\Port $_port */
		$_port = $this->ports[$port] ?? null;
		if (is_null($_port)) {
			return null;
		}
		return $_port->getCallback($event);
	}


	/**
	 * @param string $type
	 * @param string $host
	 * @param int $port
	 * @param int $mode
	 * @param array $settings
	 * @throws ReflectionException
	 * @throws ConfigException
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

		echo sprintf("\033[36m[" . date('Y-m-d H:i:s') . "]\033[0m [%s]$type service %s::%d start.", $id, $host, $port) . PHP_EOL;

		$this->addDefaultListener($settings);
	}


	/**
	 * @param int $port
	 * @throws Exception
	 */
	public function stopServer(int $port)
	{
		if (!($pid = $this->checkPortIsAlready($port))) {
			return;
		}
		while ($this->checkPortIsAlready($port)) {
			Process::kill($pid, SIGTERM);
			usleep(300);
		}
	}


	/**
	 * @param $port
	 * @return bool|string
	 * @throws Exception
	 */
	private function checkPortIsAlready($port): bool|string
	{
		if (!Kiri::getPlatform()->isLinux()) {
			exec("lsof -i :" . $port . " | grep -i 'LISTEN' | awk '{print $2}'", $output);
			if (empty($output)) return false;
			$output = explode(PHP_EOL, $output[0]);
			return $output[0];
		}

		$serverPid = file_get_contents(storage('.swoole.pid'));
		if (!empty($serverPid) && shell_exec('ps -ef | grep ' . $serverPid . ' | grep -v grep')) {
			Process::kill($serverPid, SIGTERM);
		}

		exec('netstat -lnp | grep ' . $port . ' | grep "LISTEN" | awk \'{print $7}\'', $output);
		if (empty($output)) {
			return false;
		}
		return explode('/', $output[0])[0];
	}


	/**
	 * @param array $settings
	 * @throws ReflectionException
	 * @throws Exception
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
	 * @param string $class
	 * @return object
	 */
	private function getNewInstance(string $class): object
	{
		return $this->container->create($class);
	}


	/**
	 * @param OnTaskInterface|string $handler
	 * @param array $params
	 * @param int|null $workerId
	 * @throws ReflectionException
	 * @throws Exception
	 */
	public function task(OnTaskInterface|string $handler, array $params = [], int $workerId = null)
	{
		if ($workerId === null || $workerId <= $this->server->setting['worker_num']) {
			$workerId = random_int($this->server->setting['worker_num'] + 1,
				$this->server->setting['worker_num'] + 1 + $this->server->setting['task_worker_num']);
		}
		if (is_string($handler)) {
			$implements = $this->container->getReflect($handler);
			if (!in_array(OnTaskInterface::class, $implements->getInterfaceNames())) {
				throw new Exception('Task must instance ' . OnTaskInterface::class);
			}
			$handler = $implements->newInstanceArgs($params);
		}
		$this->server->task(serialize($handler), $workerId);
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
	 * @throws ReflectionException
	 */
	private function addTaskListener(array $events = []): void
	{
		$task_use_object = $this->server->setting['task_object'] ?? $this->server->setting['task_use_object'] ?? false;
		$reflect = $this->container->getReflect(OnServerTask::class)?->newInstance();
		if ($task_use_object || $this->server->setting['task_enable_coroutine']) {
			$this->server->on('task', $events[Constant::TASK] ?? [$reflect, 'onCoroutineTask']);
		} else {
			$this->server->on('task', $events[Constant::TASK] ?? [$reflect, 'onTask']);
		}
		$this->server->on('finish', $events[Constant::FINISH] ?? [$reflect, 'onFinish']);
	}


	/**
	 * @param Port|Server $server
	 * @param array|null $settings
	 */
	public function bindCallback(Port|Server $server, ?array $settings = [])
	{
		// TODO: Implement bindCallback() method.
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
