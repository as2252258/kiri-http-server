<?php

namespace Kiri\Server\Handler;

use Exception;
use Kiri;
use Kiri\Abstracts\Config;
use Kiri\Core\Help;
use Kiri\Events\EventDispatch;
use Kiri\Server\Events\OnAfterWorkerStart;
use Kiri\Server\Events\OnBeforeWorkerStart;
use Kiri\Server\Events\OnTaskerStart as OnTaskStart;
use Kiri\Server\Events\OnWorkerError;
use Kiri\Server\Events\OnWorkerExit;
use Kiri\Server\Events\OnWorkerStart;
use Kiri\Server\Events\OnWorkerStop;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;
use Swoole\Server;


/**
 * Class OnServerWorker
 * @package Server\Worker
 */
class OnServerWorker extends \Kiri\Server\Abstracts\Server
{


	/**
	 * @param Server $server
	 * @param int $workerId
	 * @return void
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 * @throws Exception
	 */
	public function onWorkerStart(Server $server, int $workerId): void
	{
		$dispatch = \Kiri::getDi()->get(EventDispatch::class);
		$dispatch->dispatch(new OnBeforeWorkerStart($workerId));
		set_env('environmental_workerId', $workerId);

		if ($workerId < $server->setting['worker_num']) {
			$dispatch->dispatch(new OnWorkerStart($server, $workerId));
		} else {
			$dispatch->dispatch(new OnTaskStart($server, $workerId));
		}

		$dispatch->dispatch(new OnAfterWorkerStart());
	}


	/**
	 * @param Server $server
	 * @param int $workerId
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface|ReflectionException
	 */
	public function onWorkerStop(Server $server, int $workerId): void
	{
		event(new OnWorkerStop($server, $workerId));
	}


	/**
	 * @param Server $server
	 * @param int $workerId
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface|ReflectionException
	 */
	public function onWorkerExit(Server $server, int $workerId): void
	{
		event(new OnWorkerExit($server, $workerId));
	}


	/**
	 * @param Server $server
	 * @param int $worker_id
	 * @param int $worker_pid
	 * @param int $exit_code
	 * @param int $signal
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 * @throws Exception
	 */
	public function onWorkerError(Server $server, int $worker_id, int $worker_pid, int $exit_code, int $signal): void
	{
		$dispatch = \Kiri::getDi()->get(EventDispatch::class);
		$dispatch->dispatch(new OnWorkerError($server, $worker_id, $worker_pid, $exit_code, $signal));

		$message = sprintf('Worker#%d::%d error stop. signal %d, exit_code %d, msg %s',
			$worker_id, $worker_pid, $signal, $exit_code, swoole_strerror(swoole_last_error(), 9)
		);

		error($message);

		$this->system_mail($message);
	}


	/**
	 * @param $messageContent
	 * @throws Exception
	 */
	protected function system_mail($messageContent): void
	{
		try {
			$email = Config::get('email', ['enable' => false]);
			if (!empty($email) && ($email['enable'] ?? false)) {
				Help::sendEmail($email, 'Service Error', $messageContent);
			}
		} catch (\Throwable $e) {
			error($e, ['email']);
		}
	}

}
