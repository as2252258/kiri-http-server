<?php


namespace Server\Handler;


use Annotation\Inject;
use Kiri\Abstracts\Logger;
use Kiri\Exception\ConfigException;
use Kiri\Kiri;
use ReflectionException;
use Server\SInterface\OnTaskInterface;
use Swoole\Server;


/**
 * Class OnServerTask
 * @package Server\Task
 */
class OnServerTask
{


	#[Inject(Logger::class)]
	public Logger $logger;


	/**
	 * @param Server $server
	 * @param int $task_id
	 * @param int $src_worker_id
	 * @param mixed $data
	 * @throws ConfigException
	 */
	public function onTask(Server $server, int $task_id, int $src_worker_id, mixed $data)
	{
		try {
			$data = $this->resolve($data);
		} catch (\Throwable $exception) {
			$data = jTraceEx($exception);

			$this->logger->error('task', [$data]);
		} finally {
			$server->finish($data);
		}
	}


	/**
	 * @param Server $server
	 * @param Server\Task $task
	 * @throws ConfigException
	 */
	public function onCoroutineTask(Server $server, Server\Task $task)
	{
		try {
			$data = $this->resolve($task->data);
		} catch (\Throwable $exception) {
			$data = jTraceEx($exception);

			$this->logger->error('task', [$data]);
		} finally {
			$server->finish($data);
		}
	}


	/**
	 * @param $data
	 * @return null
	 * @throws ReflectionException
	 */
	private function resolve($data)
	{
		[$class, $params] = json_encode($data, true);

		$reflect = Kiri::getDi()->getReflect($class);

		if (!$reflect->isInstantiable()) {
			return null;
		}
		$class = $reflect->newInstanceArgs($params);
		return $class->execute();
	}


	/**
	 * @param Server $server
	 * @param int $task_id
	 * @param mixed $data
	 */
	public function onFinish(Server $server, int $task_id, mixed $data)
	{
		if (!($data instanceof OnTaskInterface)) {
			return;
		}
		$data->finish($server, $task_id);
	}


}
