<?php

namespace Kiri\Server\Abstracts;

use Exception;
use Kiri;
use Kiri\Abstracts\Config;
use Kiri\Di\ContainerInterface;
use Kiri\Exception\ConfigException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use ReflectionException;
use Swoole\Server;
use Kiri\Server\ServerInterface;
use Kiri\Server\Constant;
use Kiri\Events\EventDispatch;
use Kiri\Exception\NotFindClassException;
use Kiri\Server\Events\OnServerBeforeStart;

/**
 *
 */
class AsyncServer
{

	use TraitServer;


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
	 * @throws NotFindClassException
	 * @throws NotFoundExceptionInterface
	 */
	public function initCoreServers(array $service, int $daemon = 0): void
	{
		$service = $this->genConfigService($service);
		$this->createBaseServer(array_shift($service), $daemon);
		foreach ($service as $value) {
			$this->addListener($value);
		}
		$this->processManager->batch(Config::get('processes', []), $this->server);
		$this->processManager->batch($this->getProcess(), $this->server);
	}


	/**
	 * @param string $name
	 * @return Server|null
	 */
	public function getServer(string $name = ''): Server|null
	{
		return $this->server;
	}


	/**
	 * @param \Kiri\Server\Config $config
	 * @param int $daemon
	 * @return void
	 * @throws ConfigException
	 * @throws ContainerExceptionInterface
	 * @throws NotFindClassException
	 * @throws NotFoundExceptionInterface
	 */
	private function createBaseServer(\Kiri\Server\Config $config, int $daemon = 0): void
	{
		$match = $this->getServerClass($config->type);
		if (is_null($match)) {
			throw new NotFindClassException('Unknown server type ' . $config->type);
		}
		$this->server = new $match($config->host, $config->port, SWOOLE_PROCESS, $config->mode);

		$this->server->set($this->systemConfig($config, $daemon));

		$this->logger->alert('Listen ' . $config->type . ' address ' . $config->host . '::' . $config->port);

		$this->onEventListen($this->server, Config::get('server.events', []));
		$this->onEventListen($this->server, $config->events);

		$this->container->setBindings(ServerInterface::class, $this->server);
	}


	/**
	 * @param \Kiri\Server\Config $config
	 * @param int $daemon
	 * @return array
	 * @throws Exception
	 * @throws ConfigException
	 */
	protected function systemConfig(\Kiri\Server\Config $config, int $daemon): array
	{
		$settings = array_merge(Config::get('server.settings', []), $config->settings);
		$settings[Constant::OPTION_DAEMONIZE] = (bool)$daemon;
		$settings[Constant::OPTION_ENABLE_REUSE_PORT] = true;
		$settings[Constant::OPTION_PID_FILE] = storage('.swoole.pid');
		if (!isset($settings[Constant::OPTION_PID_FILE])) {
			$settings[Constant::OPTION_LOG_FILE] = storage('system.log');
		}
		return $settings;
	}


	/**
	 * @param \Kiri\Server\Config $config
	 * @return void
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 * @throws Exception
	 */
	public function addListener(\Kiri\Server\Config $config): void
	{
		$port = $this->server->addlistener($config->host, $config->port, $config->mode);
		if ($port === false) {
			throw new Exception('Listen port fail.' . swoole_last_error());
		}

		$this->logger->alert('Listen ' . $config->type . ' address ' . $config->host . '::' . $config->port);

		$port->set($this->resetSettings($config->type, $config->settings));

		$this->onEventListen($port, $config->getEvents());
		Kiri::app()->set($config->getName(), $port);
	}


	/**
	 * @param string $type
	 * @param array $settings
	 * @return array
	 */
	private function resetSettings(string $type, array $settings): array
	{
		if ($type == Constant::SERVER_TYPE_HTTP && !isset($settings['open_http_protocol'])) {
			$settings['open_http_protocol'] = true;
			if (in_array($this->server->setting['dispatch_mode'], [2, 4])) {
				$settings['open_http2_protocol'] = true;
			}
		}
		if ($type == Constant::SERVER_TYPE_WEBSOCKET && !isset($settings['open_websocket_protocol'])) {
			$settings['open_websocket_protocol'] = true;
		}
		return $settings;
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
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 * @throws ReflectionException
	 */
	public function start(): void
	{
		$this->dispatch->dispatch(new OnServerBeforeStart());
		$this->server->start();
	}


}
