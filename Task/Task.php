<?php

namespace Kiri\Server\Task;


use Kiri\Server\Constant;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;
use Swoole\Server;

class Task implements TaskInterface
{


	/**
	 * @param Server $server
	 * @return void
	 */
	public function initTaskWorker(Server $server): void
	{
		if (!isset($server->setting[Constant::OPTION_TASK_WORKER_NUM])) {
			return;
		}
		if ($server->setting[Constant::OPTION_TASK_WORKER_NUM] < 1) {
			return;
		}
		$server->on('finish', [$this, 'onFinish']);
		$server->on('task', [$this, 'onTask']);
	}


	/**
	 * @param Server $server
	 * @param int $task_id
	 * @param mixed $data
	 * @return void
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 * @throws ReflectionException
	 */
	private function onFinish(Server $server, int $task_id, mixed $data): void
	{
		event(new OnTaskFinish($task_id, $data));
	}


	/**
	 * @param Server $server
	 * @param int $task_id
	 * @param int $src_worker_id
	 * @param mixed $data
	 * @return mixed
	 * @throws ReflectionException
	 */
	private function onTask(Server $server, int $task_id, int $src_worker_id, mixed $data): mixed
	{
		$data = json_decode($data, true);
		if (is_null($data)) {
			return null;
		}
		$data[0] = \Kiri::getDi()->get($data[0]);
		return call_user_func($data, $task_id, $src_worker_id);
	}


	/**
	 * @param array $handler
	 * @param int|null $workerId
	 * @return void
	 * @throws ReflectionException
	 */
	public function dispatch(array $handler, ?int $workerId = null): void
	{
		/** @var Server $server */
		$server = \Kiri::service()->get('server');
		if (is_null($workerId)) {
			$worker = $server->setting[Constant::OPTION_TASK_WORKER_NUM];
			$workerId = rand(0, $worker);
		}
		$server->task(json_encode($handler), $workerId);
	}


}
