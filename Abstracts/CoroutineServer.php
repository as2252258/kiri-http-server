<?php

namespace Kiri\Server\Abstracts;

use Kiri\Abstracts\Config;
use Kiri\Di\ContainerInterface;
use Kiri\Events\EventDispatch;
use Kiri\Exception\ConfigException;
use Kiri\Server\Constant;
use Kiri\Server\Events\OnShutdown;
use Kiri\Server\Events\OnWorkerStart;
use Kiri\Server\Events\OnWorkerStop;
use Kiri\Server\ServerInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Server;
use function Swoole\Coroutine\run;


class CoroutineServer implements ServerInterface
{


	/**
	 * @var array<Coroutine\Server|Coroutine\Http\Server>
	 */
	private array $servers = [];


	use TraitServer;


	/**
	 * @param Config $config
	 * @param ContainerInterface $container
	 * @param EventDispatch $dispatch
	 * @param LoggerInterface $logger
	 * @param ProcessManager $processManager
	 * @param array $params
	 */
	public function __construct(public Config             $config,
	                            public ContainerInterface $container,
	                            public EventDispatch      $dispatch,
	                            public LoggerInterface    $logger,
	                            public ProcessManager     $processManager,
	                            public array              $params = []
	)
	{
	}


	/**
	 * @param string $name
	 * @return Server|\Swoole\Coroutine\Server|\Swoole\Coroutine\Http\Server|null
	 */
	public function getServer(string $name): Server|\Swoole\Coroutine\Server|\Swoole\Coroutine\Http\Server|null
	{
		return $this->servers[$name] ?? null;
	}


	/**
	 * @param array $service
	 * @param int $daemon
	 * @return void
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	public function initCoreServers(array $service, int $daemon = 0): void
	{
		// TODO: Implement initCoreServers() method.
		$service = $this->genConfigService($service);
		foreach ($service as $value) {
			$this->addListener($value);
		}
	}


	/**
	 * @param \Kiri\Server\Config $config
	 * @return void
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	public function addListener(\Kiri\Server\Config $config): void
	{
		// TODO: Implement addListener() method.
		$class = $this->getCoroutineServerClass($config->type);

		/** @var Coroutine\Server|Coroutine\Http\Server $server */
		$server = new $class($config->host, $config->port);
		$server->set($config->settings);
		if ($server instanceof Server) {
			$this->onTcpConnection($server, $config);
		} else {
			$this->onRequestCallback($server, $config);
		}

		$this->servers[$config->name] = $server;
	}


	/**
	 * @param Coroutine\Http\Server $server
	 * @param \Kiri\Server\Config $config
	 * @return void
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	public function onRequestCallback(Coroutine\Http\Server $server, \Kiri\Server\Config $config): void
	{
		$requestCallback = $config->events[Constant::REQUEST] ?? null;
		if (empty($requestCallback)) {
			return;
		}
		if (is_array($requestCallback) && is_string($requestCallback[0])) {
			$requestCallback[0] = $this->container->get($requestCallback[0]);
		}
		$server->handle('/', function (Request $request, Response $response) use ($requestCallback) {
			call_user_func($requestCallback, $request, $response);
		});
	}


	/**
	 * @param Coroutine\Server $server
	 * @param \Kiri\Server\Config $config
	 * @return void
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	public function onTcpConnection(Coroutine\Server $server, \Kiri\Server\Config $config): void
	{
		$requestCallback = $config->events[Constant::RECEIVE] ?? null;
		if (is_null($requestCallback)) {
			return;
		}
		if (is_array($requestCallback) && is_string($requestCallback[0])) {
			$requestCallback[0] = $this->container->get($requestCallback[0]);
		}
		$closeCallback = $config->events[Constant::CLOSE] ?? null;
		$server->handle(function (Coroutine\Server\Connection $connection) use ($requestCallback, $closeCallback) {

			defer(function () use ($connection, $closeCallback) {
				call_user_func($closeCallback, $connection->exportSocket()->fd);
			});
			while (true) {
				$read = $connection->recv();
				if ($read === null || $read === false) {
					break;
				}
				$requestCallback($read);
			}
		});
	}


	/**
	 * @return void
	 */
	public function start(): void
	{
		// TODO: Implement start() method.
		run(function () {
			$this->processManager->batch(Config::get('processes', []));
			$this->processManager->batch($this->getProcess());

			foreach ($this->servers as $server) {
				Coroutine::create(function () use ($server) {
					$this->runServer($server);
				});
			}
		});
	}


	/**
	 * @param Coroutine\Http\Server|Coroutine\Server $server
	 * @return void
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 * @throws \ReflectionException
	 */
	public function runServer(Coroutine\Http\Server|Coroutine\Server $server): void
	{
		$this->dispatch->dispatch(new OnWorkerStart($server, 0));

		$server->start();

		$this->dispatch->dispatch(new OnWorkerStop($server,0));

		$this->runServer($server);
	}


}
