<?php

namespace Kiri\Server;

use Exception;
use Kiri\Abstracts\Component;
use Kiri\Abstracts\Config;
use Kiri\Annotation\Inject;
use Kiri\Error\Logger;
use Kiri\Exception\ConfigException;
use Kiri\Server\Contract\OnCloseInterface;
use Kiri\Server\Contract\OnConnectInterface;
use Kiri\Server\Contract\OnDisconnectInterface;
use Kiri\Server\Contract\OnHandshakeInterface;
use Kiri\Server\Contract\OnMessageInterface;
use Kiri\Server\Contract\OnPacketInterface;
use Kiri\Server\Contract\OnReceiveInterface;
use Kiri\Server\Handler\OnPipeMessage;
use Kiri\Server\Handler\OnServer;
use Kiri\Server\Handler\OnServerManager;
use Kiri\Server\Handler\OnServerWorker;
use Kiri\Task\TaskManager;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Swoole\Http\Server as HServer;
use Swoole\Server;
use Swoole\Server\Port;
use Swoole\WebSocket\Server as WServer;


/**
 * Class OnServerManager
 * @package Http\Service
 */
class ServerManager extends Component
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

	/** @var array<string,Port> */
	public array $ports = [];

	public int $mode = SWOOLE_TCP;


	private Server|null $server = null;


	const DEFAULT_EVENT = [
		Constant::WORKER_START  => [OnServerWorker::class, 'onWorkerStart'],
		Constant::WORKER_EXIT   => [OnServerWorker::class, 'onWorkerExit'],
		Constant::WORKER_STOP   => [OnServerWorker::class, 'onWorkerStop'],
		Constant::WORKER_ERROR  => [OnServerWorker::class, 'onWorkerError'],
		Constant::MANAGER_START => [OnServerManager::class, 'onManagerStart'],
		Constant::MANAGER_STOP  => [OnServerManager::class, 'onManagerStop'],
		Constant::START         => [OnServer::class, 'onStart'],
		Constant::SHUTDOWN      => [OnServer::class, 'onShutdown'],
	];


	/**
	 * @var array|string[]
	 */
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
	 */
	public function initBaseServer($configs, int $daemon = 0): void
	{
		$context = di(ServerManager::class);
		foreach ($this->sortService($configs['ports']) as $config) {
			$this->startListenerHandler($context, $config, $daemon);
		}
		$this->bindPipeMessage();
	}


	/**
	 * @return void
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	public function bindPipeMessage(): void
	{
		$pipeMessage = $this->getContainer()->get(OnPipeMessage::class);
		$this->server->on(Constant::PIPE_MESSAGE, [$pipeMessage, 'onPipeMessage']);

		if (!isset($this->server->setting['task_worker_num']) || $this->server->setting['task_worker_num'] < 1) {
			return;
		}

		$this->getContainer()->get(TaskManager::class)->taskListener($this->server);
	}


	/**
	 * @return array
	 */
	public function getSetting(): array
	{
		return $this->server->setting;
	}


	/**
	 * @param ServerManager $context
	 * @param array $config
	 * @param int $daemon
	 * @throws ConfigException
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
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

		$this->getContainer()->setBindings(SwooleServerInterface::class, $this->server);

		$id = Config::get('id', 'system-service');

		$this->logger->debug(sprintf('[%s].' . $type . ' service %s::%d start', $id, $host, $port));

		$this->addDefaultListener($settings);
	}


	/**
	 * @param array $settings
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	private function addDefaultListener(array $settings): void
	{
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
	 */
	private function addServiceEvents(array $events, Server|Port $server)
	{
		foreach ($events as $name => $event) {
			if (is_array($event) && is_string($event[0])) {
				$event[0] = $this->getContainer()->get($event[0]);
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


}
