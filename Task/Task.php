<?php

namespace Kiri\Server\Task;


use Kiri;
use Kiri\Server\Constant;
use Kiri\Server\ServerInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;
use Swoole\Server;

class Task implements TaskInterface
{

    /**
     * @var ServerInterface
     */
    public ServerInterface $server;


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
	public function onFinish(Server $server, int $task_id, mixed $data): void
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
	public function onTask(Server $server, int $task_id, int $src_worker_id, mixed $data): mixed
	{
		$data = json_decode($data, true);
		if (is_null($data)) {
			return null;
		}
		$data[0] = Kiri::getDi()->get($data[0]);
		return call_user_func($data, $task_id, $src_worker_id);
	}


	/**
	 * @param array|string|object $handler
	 * @param int|null $workerId
	 * @return void
	 * @throws ReflectionException
	 */
	public function dispatch(array|string|object $handler, ?int $workerId = null): void
	{
		if (is_null($workerId)) {
			$workerId = rand(0, $this->server->setting[Constant::OPTION_TASK_WORKER_NUM] - 1);
		}
		if (is_string($handler)) {
            $this->server->task(serialize([di($handler), 'handle']), $workerId);
		} else if (is_array($handler)) {
			if (is_string($handler[0])) {
				$handler[0] = di($handler[0]);
			}
            $this->server->task(serialize($handler), $workerId);
		} else {
            $this->server->task(serialize([$handler, 'handle']), $workerId);
		}
	}


}
