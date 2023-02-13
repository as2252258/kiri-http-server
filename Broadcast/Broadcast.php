<?php

namespace Kiri\Server\Broadcast;

use Kiri;
use Kiri\Server\ServerInterface;
use Kiri\Server\Abstracts\ProcessManager;

class Broadcast
{


	/**
	 * @param $message
	 * @return void
	 */
	public function broadcast($message): void
	{
		$di = Kiri::getDi();
		$message = serialize(new Message($message));

		$processes = $di->get(ProcessManager::class)->getProcesses();
		foreach ($processes as $process) {
			$process->write($message);
		}

		$server = $di->get(ServerInterface::class);

		$total = $server->setting['worker_num'] + $server->setting['task_worker_num'];
		for ($i = 0; $i < $total; $i++) {
			if ($i == env('environmental_workerId')) {
				continue;
			}
			$server->sendMessage($message, $i);
		}
	}


}
