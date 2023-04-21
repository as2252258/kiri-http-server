<?php

namespace Kiri\Server\Contract;

use Swoole\Server;

/**
 *
 */
interface OnCloseInterface
{


	/**
	 * @param Server $server
	 * @param int $fd
	 * @return void
	 */
	public function onClose(Server $server, int $fd): void;


}
