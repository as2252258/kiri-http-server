<?php

namespace Kiri\Server\Abstracts;

use Exception;
use Kiri;
use Kiri\Abstracts\Config;
use Psr\Container\ContainerInterface;
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
use Kiri\Di\Inject\Container;

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
		$pid = (int)file_get_contents(storage('.swoole.pid'));
		if (posix_kill($pid, 0)) {
			posix_kill($pid, SIGTERM);
		}
		
		$this->createBaseServer(array_shift($service), $daemon);
		foreach ($service as $value) {
			$this->addListener($value);
		}
		$rpcService = Config::get('rpc', []);
		if (!empty($rpcService)) {
			$this->addListener(instance(SConfig::class, [], $rpcService));
		}

		$processManager = Kiri::getDi()->get(ProcessManager::class);
		$processManager->batch(Config::get('processes', []));

		$this->onSignal(Config::get('signal', []));
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

		$processManager = Kiri::getDi()->get(EventDispatch::class);
		$processManager->dispatch(new OnShutdown());

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
	 * @throws ReflectionException
	 */
	private function createBaseServer(SConfig $config, int $daemon = 0): void
	{
		$match = $this->getServerClass($config->type);
		if (is_null($match)) {
			throw new NotFindClassException('Unknown server type ' . $config->type);
		}
		$this->server = new $match($config->host, $config->port, $config->mode, $config->socket);

		$this->server->set($this->systemConfig($config, $daemon));

		\Kiri::getLogger()->alert('Listen ' . $config->type . ' address ' . $config->host . '::' . $config->port);

		$this->onEventListen($this->server, Config::get('server.events', []));
		$this->onEventListen($this->server, $config->events);
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
	 * @throws Exception
	 */
	public function addListener(SConfig $config): void
	{
		$port = $this->server->addlistener($config->host, $config->port, $config->mode);
		if ($port === false) {
			throw new Exception('Listen port fail.' . swoole_last_error());
		}

		\Kiri::getLogger()->alert('Listen ' . $config->type . ' address ' . $config->host . '::' . $config->port);

		$port->set($this->resetSettings($config->type, $config->settings));

		$this->onEventListen($port, $config->getEvents());
		Kiri::getDi()->get(LocalService::class)->set($config->getName(), $port);
	}

	/**
	 * @param $no
	 * @param array $signInfo
	 * @return void
	 * @throws ReflectionException
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
	 * @throws ReflectionException
	 */
	private function onEventListen(Server\Port|Server $base, array $events): void
	{
		foreach ($events as $name => $event) {
			if (is_array($event) && is_string($event[0])) {
				$event[0] = Kiri::getDi()->get($event[0]);
			}
			$base->on($name, $event);
		}
	}


	/**
	 * @return void
	 * @throws
	 */
	public function start(): void
	{
		$processManager = Kiri::getDi()->get(EventDispatch::class);
		$processManager->dispatch(new OnServerBeforeStart());
		$this->server->start();
	}


}
