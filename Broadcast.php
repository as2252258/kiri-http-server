<?php

namespace Kiri\Server;

use Kiri\Kiri;

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

		$server = $di->get(SwooleServerInterface::class);

		$total = $server->setting['worker_num'] + $server->setting['task_worker_num'];
		for ($i = 0; $i < $total; $i++) {
			$server->sendMessage($message, $i);
		}
	}


}
