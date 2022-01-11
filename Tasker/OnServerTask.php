<?php


namespace Kiri\Server\Tasker;


use Kiri\Annotation\Inject;
use Kiri\Abstracts\Logger;
use Kiri\Exception\ConfigException;
use Kiri\Server\Contract\OnTaskInterface;
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

			$this->logger->error('task', [error_trigger_format($exception)]);
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

			$this->logger->error('task', [error_trigger_format($exception)]);
		} finally {
			$task->finish($data);
		}
	}


	/**
	 * @param $data
	 * @return null
	 */
	private function resolve($data)
	{
		$execute = unserialize($data);
		if ($execute instanceof OnTaskInterface) {
			return $execute->execute();
		}
		return null;
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
