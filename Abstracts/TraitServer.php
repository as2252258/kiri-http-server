<?php

namespace Kiri\Server\Abstracts;

use Swoole\Http\Server as HServer;
use Swoole\Server;
use Swoole\WebSocket\Server as WServer;

trait TraitServer
{


	private array $_process = [];


	/**
	 * @param $class
	 * @return void
	 */
	public function addProcess($class): void
	{
		$this->_process[] = $class;
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
	 */
	public function genConfigService(array $ports): array
	{
		$array = [];
		foreach ($ports as $port) {
			$config = \Kiri::getDi()->create(Config::class, [], $port);
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
			Constant::SERVER_TYPE_BASE, Constant::SERVER_TYPE_TCP, Constant::SERVER_TYPE_UDP => Server::class,
			Constant::SERVER_TYPE_HTTP, Constant::SERVER_TYPE_WEBSOCKET => \Swoole\Coroutine\Http\Server::class,
			default => null
		};
	}


}