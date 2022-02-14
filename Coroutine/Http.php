<?php

namespace Kiri\Server\Coroutine;

use Kiri\Abstracts\Component;
use Kiri\Exception\ConfigException;
use Kiri\Message\Handler\Router;
use Kiri\Server\Constant;
use Kiri\Server\ProcessManager;
use Kiri\Server\TraitServer;
use Kiri\Task\AsyncTaskExecute;
use Kiri\Task\CoroutineTaskExecute;
use Kiri\Websocket\FdCollector;
use Kiri\Websocket\Sender;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Swoole\Coroutine;
use Swoole\Exception;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\WebSocket\Frame;
use function Swoole\Coroutine\run;

class Http extends Component
{

	use TraitServer;

	private array $servers = [];


	private array $configs = [];


	public Router $collector;


	public function init()
	{
		$this->collector = \Kiri::getDi()->get(Router::class);
	}


	/**
	 * @return void
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	public function onShutdown(): void
	{
		$process = $this->getContainer()->get(ProcessManager::class);
		$process->stop();

		foreach ($this->servers as $server) {
			$server->shutdown();
		}
	}


	public function shutdown()
	{

	}


	/**
	 * @param array $config
	 * @return void
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 * @throws ConfigException
	 *
	 * 异步任务进程
	 */
	public function startTaskWorker(array $config)
	{
		$task_worker_num = $config['settings']['task_worker_num'] ?? 0;
		if ($task_worker_num < 1) {
			return;
		}
		$task_enable_coroutine = $config['settings']['task_enable_coroutine'];
		if ($task_enable_coroutine) {
			$tasker = $this->getContainer()->get(CoroutineTaskExecute::class);
		} else {
			$tasker = $this->getContainer()->get(AsyncTaskExecute::class);
		}
		$tasker->setTotal($task_worker_num);
		$tasker->start();
	}


	/**
	 * @param array $config
	 * @param $daemon
	 * @return void
	 * @throws ContainerExceptionInterface
	 * @throws Exception
	 * @throws NotFoundExceptionInterface
	 */
	public function initBaseServer(array $config, $daemon)
	{
		$this->configs = $config;
		foreach ($config['ports'] as $value) {
			$this->_addListener($value);
		}
	}


	public function addListener(string $type, string $host, int $port, int $mode, array $settings = [])
	{

	}


	/**
	 * @param $value
	 * @return void
	 * @throws ContainerExceptionInterface
	 * @throws Exception
	 * @throws NotFoundExceptionInterface
	 */
	protected function _addListener($value)
	{
		$value = $this->resolveCallback($value);
		if ($value['type'] == Constant::SERVER_TYPE_HTTP) {
			$onRequest = $value['events'][Constant::REQUEST] ?? null;
			if (is_null($onRequest)) {
				throw new Exception('Server callback con\'t null.');
			}
			$server = new Coroutine\Http\Server($value['host'], $value['port'], null, true);

			$this->servers[$value['port']] = $server;

			$server->handle('/', function (Request $request, Response $response) use ($onRequest) {
				call_user_func($onRequest, $request, $response);
			});
		} else if ($value['type'] == Constant::SERVER_TYPE_WEBSOCKET) {
			$handshake = $value['events'][Constant::HANDSHAKE] ?? null;

			$open = $value['events'][Constant::OPEN] ?? null;

			$close = $value['events'][Constant::CLOSE] ?? null;
			$message = $value['events'][Constant::MESSAGE] ?? null;
			if (is_null($message)) {
				throw new Exception('Server callback con\'t null.');
			}

			$server = new Coroutine\Http\Server($value['host'], $value['port'], null, true);

			$sender = $this->getContainer()->get(Sender::class);
			$sender->setServer($server);

			$this->servers[$value['port']] = $server;

			$server->handle('/', function (Request $request, Response $response) use ($handshake, $open, $close, $message) {
				$fdCollector = $this->getContainer()->get(FdCollector::class);
				try {
					$response->upgrade();
					$fdCollector->set($response->fd, $response);
					if (is_callable($open)) {
						$open($request);
					}
					while (($data = $response->recv()) instanceof Frame) {
						try {
							call_user_func($message, $data);
						} catch (\Throwable $throwable) {
							$this->logger()->error($throwable->getMessage());
						}
					}
					call_user_func($close, $response->fd);
				} catch (\Throwable $throwable) {
					$this->logger()->error($throwable->getMessage());
				}
			});
		} else {
			$message = $value['events'][Constant::RECEIVE] ?? null;
			if (is_null($message)) {
				throw new Exception('Server callback con\'t null.');
			}
			$conn = $value['events'][Constant::CONNECT] ?? null;
			$close = $value['events'][Constant::CLOSE] ?? null;

			$server = new Coroutine\Server($value['host'], $value['port'], null, true);

			$this->servers[$value['port']] = $server;

			$server->handle(function (Coroutine\Server\Connection $connection) use ($message, $conn, $close) {
				if (!is_null($conn)) {
					call_user_func($conn, $connection);
				}
				while (true) {
					$data = $connection->recv(1024);
					if ($data === '' || $data === false) {
						defer(function () use ($close, $connection) {
							call_user_func($close, $connection);
						});
						$connection->close();
						break;
					}
					call_user_func($message, $data);
				}
			});
		}
	}


	public function start()
	{
		run(function () {
			$this->startTaskWorker($this->configs);

			$this->collector->scan_build_route();

			foreach ($this->servers as $value) {
				Coroutine::create(function () use ($value) {
					$value->start();
				});
			}
		});
	}


	/**
	 * @param array $config
	 * @return array
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	protected function resolveCallback(array $config): array
	{
		if (!isset($config['events'])) {
			return $config;
		}
		foreach ($config['events'] as $key => $event) {
			if (is_array($event)) {
				if (is_string($event[0])) {
					$event[0] = $this->getContainer()->get($event[0]);
					$config['events'][$key] = $event;
				}
			}
		}
		return $config;
	}


}
