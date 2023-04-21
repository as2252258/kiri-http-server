<?php

namespace Kiri\Server\Abstracts;

use Exception;
use Kiri\Server\CoroutineServer;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;
use Swoole\Coroutine;
use Swoole\Http\Server as HServer;
use Swoole\Server;
use Kiri\Server\Constant;
use Kiri\Server\Config;
use Swoole\WebSocket\Server as WServer;

trait TraitServer
{
	
	
	private array $_process = [];
	
	
	/**
	 * @param string|array|BaseProcess $class
	 * @return void
	 * @throws Exception
	 */
	public function addProcess(string|array|BaseProcess $class): void
	{
		$container = \Kiri::getDi()->get(ProcessManager::class);
		
		if (!is_array($class)) {
			$class = [$class];
		}
		foreach ($class as $name) {
			$container->add($name);
		}
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
		$this->onPcntlSignal(SIGINT, [$this, 'onSigint']);
		foreach ($signal as $sig => $value) {
			if (is_array($value) && is_string($value[0])) {
				$value[0] = $this->container->get($value[0]);
			}
			if (!is_callable($value, true)) {
				throw new Exception('Register signal callback must can exec.');
			}
			$this->onPcntlSignal($sig, $value);
		}
	}
	
	
	/**
	 * @param $signal
	 * @param $callback
	 * @return void
	 */
	private function onPcntlSignal($signal, $callback): void
	{
		if (get_called_class() != CoroutineServer::class) {
			pcntl_signal(SIGINT, [$this, 'onSigint']);
		} else {
			Coroutine::create(static function () use ($signal, $callback) {
				$data = Coroutine::waitSignal($signal);
				if ($data) {
					$callback($signal, [true]);
				}
			});
		}
	}
	
	
	/**
	 * @return array
	 */
	public function getProcess(): array
	{
		return $this->_process;
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
	 * @param array $ports
	 * @return array<Config>
	 * @throws ReflectionException
	 */
	public function genConfigService(array $ports): array
	{
		$array = [];
		$ports = $ports['ports'] ?? [];
		foreach ($ports as $port) {
			$config = \Kiri::getDi()->make(Config::class, [], $port);
			if ($port['type'] == Constant::SERVER_TYPE_WEBSOCKET) {
				array_unshift($array, $config);
			} else if ($port['type'] == Constant::SERVER_TYPE_HTTP) {
				if (!empty($array) && $array[0]['type'] == Constant::SERVER_TYPE_WEBSOCKET) {
					$array[] = $config;
				} else {
					array_unshift($array, $config);
				}
			} else {
				$array[] = $config;
			}
		}
		return $array;
	}
	
	
	/**
	 * @param $type
	 * @return string|null
	 */
	public function getServerClass($type): ?string
	{
		return match ($type) {
			Constant::SERVER_TYPE_BASE, Constant::SERVER_TYPE_TCP,
			Constant::SERVER_TYPE_UDP => Server::class,
			Constant::SERVER_TYPE_HTTP => HServer::class,
			Constant::SERVER_TYPE_WEBSOCKET => WServer::class,
			default => null
		};
	}
	
	
	/**
	 * @param $type
	 * @return string|null
	 */
	public function getCoroutineServerClass($type): ?string
	{
		return match ($type) {
			Constant::SERVER_TYPE_BASE, Constant::SERVER_TYPE_TCP, Constant::SERVER_TYPE_UDP => Coroutine\Server::class,
			Constant::SERVER_TYPE_HTTP, Constant::SERVER_TYPE_WEBSOCKET => Coroutine\Http\Server::class,
			default => null
		};
	}
	
	
}
