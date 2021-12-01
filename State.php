<?php

namespace Server;

use Exception;
use Kiri\Abstracts\BaseObject;
use Kiri\Abstracts\Config;
use Swoole\Process;

class State extends BaseObject
{

	use TraitServer;


	public array $servers = [];


	public function init()
	{
		$this->servers = Config::get('servers.ports');
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
			Process::kill($pid, SIGTERM);
			usleep(300);
		}
	}

}
