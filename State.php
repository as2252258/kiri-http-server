<?php

namespace Kiri\Server;

use Exception;
use Kiri\Abstracts\Component;
use Kiri\Abstracts\Config;
use Kiri\Server\Abstracts\TraitServer;
use Swoole\Process;

class State extends Component
{

	use TraitServer;


	public array $servers = [];


	public function init()
	{
		$this->servers = Config::get('server.ports');
	}


	/**
	 * @return bool
	 * @throws Exception
	 */
	public function isRunner(): bool
	{
		$ports = $this->sortService($this->servers);
		foreach ($ports as $config) {
			if (checkPortIsAlready($config['port'])) {
				return true;
			}
		}
		return false;
	}


	/**
	 * @param $port
	 * @throws Exception
	 */
	public function exit($port)
	{
		if (!($pid = checkPortIsAlready($port))) {
			return;
		}
		while (checkPortIsAlready($port)) {
            Process::kill($pid, 0) && Process::kill($pid, SIGTERM);
			usleep(300);
		}
	}

}
