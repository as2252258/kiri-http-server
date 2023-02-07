<?php

namespace Kiri\Server\Abstracts;

use Exception;
use Kiri;
use Kiri\Abstracts\Config;
use Kiri\Di\ContainerInterface;
use Kiri\Exception\ConfigException;
use Kiri\Server\Events\OnShutdown;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use ReflectionException;
use Kiri\Server\Config as SConfig;
use Kiri\Di\LocalService;
use Swoole\Server;
use Kiri\Server\ServerInterface;
use Kiri\Server\Constant;
use Kiri\Events\EventDispatch;
use Kiri\Exception\NotFindClassException;
use Kiri\Server\Events\OnServerBeforeStart;

/**
 *
 */
class AsyncServer implements ServerInterface
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
	 * @throws Exception
	 */
	public function initCoreServers(array $service, int $daemon = 0): void
	{
		$service = $this->genConfigService($service);
		$this->createBaseServer(array_shift($service), $daemon);
		foreach ($service as $value) {
			$this->addListener($value);
		}
		$rpcService = Config::get('rpc', []);
		if (!empty($rpcService)) {
			$this->addListener(instance(SConfig::class, [], $rpcService));
		}
		$this->processManager->batch(Config::get('processes', []));
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
	 * @return bool
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface|ReflectionException
	 */
	public function shutdown(): bool
	{
		$this->server->shutdown();

		$this->dispatch->dispatch(new OnShutdown());

		return true;
	}


	/**
	 * @param SConfig $config
	 * @param int $daemon
	 * @return void
	 * @throws ConfigException
	 * @throws ContainerExceptionInterface
	 * @throws NotFindClassException
	 * @throws NotFoundExceptionInterface
	 */
	private function createBaseServer(SConfig $config, int $daemon = 0): void
	{
		$match = $this->getServerClass($config->type);
		if (is_null($match)) {
			throw new NotFindClassException('Unknown server type ' . $config->type);
		}
		$this->server = new $match($config->host, $config->port, $config->mode, $config->socket);

		$this->server->set($this->systemConfig($config, $daemon));

		$this->logger->alert('Listen ' . $config->type . ' address ' . $config->host . '::' . $config->port);

		$this->onEventListen($this->server, Config::get('server.events', []));
		$this->onEventListen($this->server, $config->events);

		$this->container->setBindings(ServerInterface::class, $this->server);
	}


	/**
	 * @param SConfig $config
	 * @param int $daemon
	 * @return array
	 * @throws Exception
	 * @throws ConfigException
	 */
	protected function systemConfig(SConfig $config, int $daemon): array
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
	 * @param SConfig $config
	 * @return void
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 * @throws Exception
	 */
	public function addListener(SConfig $config): void
	{
		$port = $this->server->addlistener($config->host, $config->port, $config->mode);
		if ($port === false) {
			throw new Exception('Listen port fail.' . swoole_last_error());
		}

		$this->logger->alert('Listen ' . $config->type . ' address ' . $config->host . '::' . $config->port);

		$port->set($this->resetSettings($config->type, $config->settings));

		$this->onEventListen($port, $config->getEvents());
		$this->container->get(LocalService::class)->set($config->getName(), $port);
	}


	/**
	 * @param array $signal
	 * @return void
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 * @throws Exception
	 */
	public function onSignal(array $signal): void
	{
		pcntl_signal(SIGINT, [$this, 'onSigint']);
		foreach ($signal as $sig => $value) {
			if (is_array($value) && is_string($value[0])) {
				$value[0] = $this->container->get($value[0]);
			}
			if (!is_callable($value, true)) {
				throw new Exception('Register signal callback must can exec.');
			}
			pcntl_signal($sig, $value);
		}
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
