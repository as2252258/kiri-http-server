<?php

namespace Kiri\Server\Events;

use Swoole\Process;

class OnProcessStop
{

	/**
	 * @param Process $process
	 */
	public function __construct(Process $process)
	{
	}

}
