<?php


namespace Server;

use Exception;
use Http\Handler\Abstracts\HttpService;
use JetBrains\PhpStorm\Pure;
use Kiri\Abstracts\Config;
use Kiri\Events\EventDispatch;
use Kiri\Exception\ConfigException;
use Note\Inject;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Server\Events\OnShutdown;


defined('PID_PATH') or define('PID_PATH', APP_PATH . 'storage/server.pid');

/**
 * Class Server
 * @package Http
 */
class Server extends HttpService
{

	private array $process = [
	];


	/**
	 * @Inject ServerManager
	 * @var null|ServerManager
	 */
	#[Inject(ServerManager::class)]
	public ?ServerManager $manager = null;

	private mixed $daemon = 0;


	/** @var EventDispatch */
	#[Inject(EventDispatch::class)]
	public EventDispatch $eventDispatch;


	/**
	 * @var State
	 */
	#[Inject(State::class)]
	public State $state;


	/**
	 *
	 */
	public function init()
	{
	}


	/**
	 * @param $process
	 */
	public function addProcess($process)
	{
		$this->process[] = $process;
	}


	/**
	 * @return string
	 * @throws ConfigException
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 * @throws \ReflectionException
	 * @throws Exception
	 */
	public function start(): string
	{
		$this->manager->initBaseServer(Config::get('server', [], true), $this->daemon);

		$rpcService = Config::get('rpc', []);
		if (!empty($rpcService)) {
			$this->manager->addListener($rpcService['type'], $rpcService['host'], $rpcService['port'],
				$rpcService['mode'], $rpcService);
		}

		$processes = array_merge($this->process, Config::get('processes', []));
		foreach ($processes as $process) {
			$this->manager->addProcess($process);
		}

		return $this->manager->getServer()->start();
	}


	/**
	 * @return void
	 *
	 * start server
	 * @throws Exception
	 */
	public function shutdown()
	{
		$configs = Config::get('server', [], true);
		foreach ($this->manager->sortService($configs['ports'] ?? []) as $config) {
			$this->state->exit($config['port']);
		}
		$this->eventDispatch->dispatch(new OnShutdown());
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
	 * @return \Swoole\Http\Server|\Swoole\Server|\Swoole\WebSocket\Server|null
	 */
	#[Pure] public function getServer(): \Swoole\Http\Server|\Swoole\Server|\Swoole\WebSocket\Server|null
	{
		return $this->manager->getServer();
	}

}
