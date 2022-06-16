<?php

namespace Kiri\Server\Broadcast;

use Kiri;
use Kiri\Server\ProcessManager;
use Kiri\Server\ServerInterface;

class Broadcast
{


	/**
	 * @param $message
	 * @return void
	 */
	public function broadcast($message)
	{
		$di = Kiri::getDi();
		$di->get(ProcessManager::class)->push($message);

		$server = $di->get(ServerInterface::class);

		$total = $server->setting['worker_num'] + $server->setting['task_worker_num'];
		for ($i = 0; $i < $total; $i++) {
			if ($i == env('environmental_workerId')) {
				continue;
			}
			$server->sendMessage(serialize(new Message($message)), $i);
		}
	}


}
