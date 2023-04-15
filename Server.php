<?php


namespace Kiri\Server;

use Exception;
use Kiri;
use Kiri\Abstracts\Config;
use Kiri\Events\EventDispatch;
use Kiri\Events\EventProvider;
use Kiri\Exception\ConfigException;
use Kiri\Router\Router;
use Kiri\Server\Events\OnShutdown;
use Kiri\Server\Events\OnWorkerStart;
use Kiri\Server\Events\OnTaskerStart;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Kiri\Server\Events\OnWorkerStop;
use Swoole\Coroutine;
use Kiri\Server\Abstracts\ProcessManager;
use Kiri\Server\Abstracts\AsyncServer;
use Kiri\Di\Inject\Container;


defined('PID_PATH') or define('PID_PATH', APP_PATH . 'storage/server.pid');


/**
 * Class Server
 * @package Http
 */
class Server
{

	private mixed $daemon = 0;


	public AsyncServer|CoroutineServer $manager;

	#[Container(State::class)]
	public State $state;

	#[Container(ContainerInterface::class)]
	public ContainerInterface $container;

	#[Container(ProcessManager::class)]
	public ProcessManager $processManager;

	#[Container(EventDispatch::class)]
	public EventDispatch $dispatch;

	#[Container(EventProvider::class)]
	public EventProvider $provider;

	#[Container(Router::class)]
	public Router $router;


	/**
	 * @return void
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	public function init(): void
	{
		$this->manager = $this->container->get(Config::get('server.type', AsyncServer::class));

		$enable_coroutine = Config::get('server.settings.enable_coroutine', false);
		if (!$enable_coroutine) {
			return;
		}
		Coroutine::set([
			'hook_flags'            => SWOOLE_HOOK_ALL ^ SWOOLE_HOOK_BLOCKING_FUNCTION,
			'enable_deadlock_check' => FALSE,
			'exit_condition'        => function () {
				return Coroutine::stats()['coroutine_num'] === 0;
			}
		]);
	}


	/**
	 * @param $process
	 * @throws Exception
	 */
	public function addProcess($process): void
	{
		$this->processManager->add($process);
	}


	/**
	 * @return void
	 * @throws ConfigException
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 * @throws Exception
	 */
	public function start(): void
	{
		$this->onHotReload();
		$this->manager->initCoreServers(Config::get('server', [], true), $this->daemon);
		$this->manager->start();
	}


	/**
	 * @return void
	 * @throws Exception
	 */
	protected function onWorkerListener(): void
	{
		$this->provider->on(OnWorkerStop::class, '\Swoole\Timer::clearAll', 9999);
		$this->provider->on(OnWorkerStart::class, [$this, 'setWorkerName']);
		$this->provider->on(OnTaskerStart::class, [$this, 'setTaskerName']);
	}


	/**
	 * @param OnWorkerStart $onWorkerStart
	 * @throws ConfigException
	 */
	public function setWorkerName(OnWorkerStart $onWorkerStart): void
	{
		if (!property_exists($onWorkerStart->server, 'worker_pid')) {
			return;
		}
		$prefix = sprintf('Worker Process[%d].%d', $onWorkerStart->server->worker_pid, $onWorkerStart->workerId);
		set_env('environmental', Kiri::WORKER);

		Kiri::setProcessName($prefix);
	}


	/**
	 * @param OnTaskerStart $onWorkerStart
	 * @throws ConfigException
	 */
	public function setTaskerName(OnTaskerStart $onWorkerStart): void
	{
		if (!property_exists($onWorkerStart->server, 'worker_pid')) {
			return;
		}
		$prefix = sprintf('Tasker Process[%d].%d', $onWorkerStart->server->worker_pid, $onWorkerStart->workerId);
		set_env('environmental', Kiri::TASK);

		Kiri::setProcessName($prefix);
	}


	/**
	 * @return void
	 * @throws ConfigException
	 * @throws Exception
	 */
	public function onHotReload(): void
	{
		$this->onWorkerListener();
		$this->router->scan_build_route();
	}


	/**
	 * @return void
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 * @throws Exception
	 */
	public function shutdown(): void
	{
		$configs = Config::get('server', [], true);
		foreach ($this->manager->sortService($configs['ports'] ?? []) as $config) {
			$this->state->exit($config['port']);
		}
		$this->dispatch->dispatch(new OnShutdown());
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
}
